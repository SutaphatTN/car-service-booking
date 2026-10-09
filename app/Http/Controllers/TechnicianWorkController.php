<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\TechnicianJob;
use App\Support\WorkSchedule;
use App\Support\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ปริมาณงานช่าง — ปฏิทินแบบตารางเรียน (เวลาเป็นแกนนอน) + ลง/แก้/ลบงาน
 *
 *  - รายวัน : แถว = ช่างแต่ละคน ในวันที่เลือก
 *  - รายคน  : แถว = วันแต่ละวัน ของช่างคนที่เลือก ในช่วงวันที่เลือก (ไม่เกิน MAX_RANGE_DAYS วัน)
 *
 * กันแอบลงงานเพิ่มเพื่อค่าคอม: ลงงานย้อนหลังได้เฉพาะ admin
 * คนอื่นแก้/ลบได้เฉพาะงานที่ตัวเองลงและยังไม่ใช่งานย้อนหลัง (ดู TechnicianJob::editableBy)
 */
class TechnicianWorkController extends Controller
{
    /** มุมมองรายคนดูได้ยาวสุดกี่วัน — ยาวกว่านี้ตารางยาวจนอ่านไม่ไหว ให้ใช้ Excel แทน */
    public const MAX_RANGE_DAYS = 31;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ], [
            'date.*' => 'รูปแบบวันที่ไม่ถูกต้อง',
        ]);

        $date = Carbon::parse($filters['date'] ?? today()->toDateString())->startOfDay();

        $jobs = TechnicianJob::with(['technician', 'creator'])
            ->whereDate('work_date', $date)
            ->orderBy('start_time')->orderBy('id')
            ->get();

        // ช่างที่ยังอยู่ทุกคน + ช่างที่ลบไปแล้วแต่มีงานในวันนั้น (ดูย้อนหลังต้องยังเห็น)
        $technicians = Technician::ordered()->get()
            ->concat($jobs->pluck('technician')->filter(fn (Technician $t) => $t->trashed())->unique('id'))
            ->values();

        $canAdd = $this->canAddOn($request, $date);
        $byTechnician = $jobs->groupBy('technician_id');

        return view('work.index', $this->shared($request, $jobs) + [
            'date' => $date,
            'technicians' => $technicians,
            'rows' => $technicians->map(fn (Technician $technician) => [
                'label' => $technician->name,
                'link' => route('work.person', [
                    'technician' => $technician->id,
                    'from' => $date->copy()->startOfWeek()->toDateString(),
                    'to' => $date->copy()->endOfWeek()->toDateString(),
                ]),
                'note' => $technician->trashed() ? 'ลบออกแล้ว' : null,
                'jobs' => $byTechnician->get($technician->id, collect()),
                'technician_id' => $technician->id,
                'date' => $date->toDateString(),
                'can_add' => $canAdd && ! $technician->trashed(),
            ]),
            'formDate' => ($canAdd ? $date : today())->toDateString(),
            'canAdd' => $canAdd,
        ]);
    }

    /** มุมมองรายคน — ช่างหนึ่งคน หลายวัน */
    public function person(Request $request): View
    {
        [$technician, $from, $to, $clipped] = $this->personFilters($request);

        $technicians = Technician::ordered()->get();
        $jobs = $technician ? $this->jobsOf($technician, $from, $to) : collect();
        $byDate = $jobs->groupBy(fn (TechnicianJob $job) => $job->work_date->toDateString());

        // ช่างที่ลบไปแล้วดูงานเก่าได้ ต้องมีชื่อในตัวเลือกด้วย
        if ($technician?->trashed()) {
            $technicians->push($technician);
        }

        $rows = collect();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $rows->push([
                'label' => thai_date($day, short: true),
                'weekday' => ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'][$day->dayOfWeek],
                'link' => route('work.index', ['date' => $key]),
                'note' => $day->isToday() ? 'วันนี้' : null,
                'jobs' => $byDate->get($key, collect()),
                'technician_id' => $technician?->id,
                'date' => $key,
                'can_add' => $technician && ! $technician->trashed() && $this->canAddOn($request, $day),
                'weekend' => $day->isWeekend(),
            ]);
        }

        return view('work.person', $this->shared($request, $jobs) + [
            'technician' => $technician,
            'technicians' => $technicians,
            'from' => $from,
            'to' => $to,
            'clipped' => $clipped,
            'rows' => $rows,
            'list' => $jobs,
            // วันตั้งต้นในฟอร์มลงงาน — ช่วงที่ดูมีวันนี้ก็ใช้วันนี้ ไม่งั้นวันแรกของช่วง (ถ้าลงย้อนหลังไม่ได้ก็วันนี้)
            'formDate' => (today()->between($from, $to) || ! $this->canAddOn($request, $from) ? today() : $from)->toDateString(),
        ]);
    }

    /** ดาวน์โหลดงานของช่างคนเดียวในช่วงวันที่เป็น Excel — ใช้คิดค่าคอม */
    public function personExport(Request $request): BinaryFileResponse
    {
        [$technician, $from, $to] = $this->personFilters($request, limit: false);
        abort_unless($technician, 404);

        $jobs = $this->jobsOf($technician, $from, $to);

        $rows = [['วันที่', 'เริ่ม', 'เสร็จ', 'ชั่วโมง', 'ประเภทงาน', 'ป้ายทะเบียน', 'หมายเหตุ', 'ผู้บันทึก']];
        foreach ($jobs as $job) {
            $rows[] = [
                thai_date($job->work_date, short: true),
                $job->startLabel(),
                $job->endLabel(),
                round($job->durationMinutes() / 60, 2),
                $job->job_type,
                $job->license_plate,
                $job->note,
                $job->creator?->name,
            ];
        }

        $summary = [['ประเภทงาน', 'จำนวนงาน', 'ชั่วโมงรวม']];
        foreach (TechnicianJob::TYPES as $type) {
            $ofType = $jobs->where('job_type', $type);
            $summary[] = [$type, $ofType->count(), round($ofType->sum(fn ($job) => $job->durationMinutes()) / 60, 2)];
        }
        $summary[] = ['รวม', $jobs->count(), round($jobs->sum(fn ($job) => $job->durationMinutes()) / 60, 2)];

        return (new XlsxWriter)
            ->addSheet('รายการ', $rows, [13, 8, 8, 9, 11, 16, 50, 18])
            ->addSheet('สรุป', $summary, [14, 12, 12])
            ->download('งานช่าง_'.$technician->name.'_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.xlsx');
    }

    public function store(Request $request): RedirectResponse
    {
        $job = TechnicianJob::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->to($this->backUrl($request, $job))
            ->with('status', 'ลงงาน '.$job->job_type.' ของ '.$job->technician->name
                .' เวลา '.$job->startLabel().'–'.$job->endLabel().' น. แล้ว');
    }

    public function update(Request $request, TechnicianJob $job): RedirectResponse
    {
        $this->authorizeEdit($request, $job);

        $job->update($this->validated($request, $job));

        return redirect()->to($this->backUrl($request, $job))
            ->with('status', 'แก้ไขงานของ '.$job->technician->name.' แล้ว');
    }

    public function destroy(Request $request, TechnicianJob $job): RedirectResponse
    {
        $this->authorizeEdit($request, $job);

        $job->delete();

        return redirect()->to($this->backUrl($request, $job))
            ->with('status', 'ลบงาน '.$job->job_type.' ของ '.$job->technician->name.' แล้ว');
    }

    /**
     * ข้อมูลที่ทั้งสองมุมมองใช้เหมือนกัน — ช่วงเวลาบนแกนนอน สรุปตามประเภท และของที่ป็อปอัปลงงานต้องใช้
     *
     * @param  Collection<int, TechnicianJob>  $jobs
     * @return array<string, mixed>
     */
    private function shared(Request $request, Collection $jobs): array
    {
        // ปกติตามเวลาทำการ งานไหนเลยออกไปก็ขยายเป็นชั่วโมงเต็มให้ครอบ
        $start = min(WorkSchedule::DAY_START, intdiv((int) $jobs->min(fn ($job) => $job->startMinutes()) ?: WorkSchedule::DAY_START, 60) * 60);
        $end = max(WorkSchedule::DAY_END, (int) ceil(((int) $jobs->max(fn ($job) => $job->endMinutes())) / 60) * 60);

        return [
            'allJobs' => $jobs,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'summary' => [
                'jobs' => $jobs->count(),
                'minutes' => $jobs->sum(fn ($job) => $job->durationMinutes()),
                'types' => collect(TechnicianJob::TYPES)->mapWithKeys(
                    fn (string $type) => [$type => $jobs->where('job_type', $type)->count()]
                ),
            ],
            'canBackdate' => $request->user()->canBackdateWork(),
            'timeOptions' => WorkSchedule::timeOptions(),
            'openAdd' => $request->boolean('add'),
        ];
    }

    /**
     * ตัวกรองของมุมมองรายคน — ค่าตั้งต้นคือช่างคนแรก สัปดาห์นี้ (จันทร์–อาทิตย์)
     *
     * @return array{0: ?Technician, 1: Carbon, 2: Carbon, 3: bool}
     */
    private function personFilters(Request $request, bool $limit = true): array
    {
        $filters = $request->validate([
            'technician' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ], [
            'from.*' => 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง',
            'to.*' => 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง',
        ]);

        $from = Carbon::parse($filters['from'] ?? today()->startOfWeek()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? today()->endOfWeek()->toDateString())->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // เลือกช่วงยาวเกินบนหน้าจอ — ตัดให้เหลือ MAX_RANGE_DAYS วันแรก (Excel ไม่จำกัด)
        $clipped = $limit && $from->diffInDays($to) >= self::MAX_RANGE_DAYS;
        if ($clipped) {
            $to = $from->copy()->addDays(self::MAX_RANGE_DAYS - 1);
        }

        // ระบุช่างมา = ต้องเป็นช่างของยี่ห้อนี้ (รวมคนที่ลบไปแล้ว ดูงานย้อนหลังได้)
        $technician = isset($filters['technician'])
            ? Technician::withTrashed()->findOrFail($filters['technician'])
            : Technician::ordered()->first();

        return [$technician, $from, $to, $clipped];
    }

    /** @return Collection<int, TechnicianJob> */
    private function jobsOf(Technician $technician, Carbon $from, Carbon $to): Collection
    {
        return TechnicianJob::with(['technician', 'creator'])
            ->where('technician_id', $technician->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')->orderBy('start_time')->orderBy('id')
            ->get();
    }

    private function canAddOn(Request $request, Carbon $date): bool
    {
        return $request->user()->canBackdateWork() || ! $date->lt(today());
    }

    /**
     * บันทึกแล้วกลับไปหน้าที่กดมา (ฟอร์มส่ง _back มา) — รับเฉพาะลิงก์ในเมนูงานช่าง
     * ไม่มีหรือไม่ถูกต้อง ก็ไปหน้ารายวันของวันที่ของงานนั้น
     */
    private function backUrl(Request $request, TechnicianJob $job): string
    {
        $back = (string) $request->input('_back');

        if (str_starts_with($back, route('work.index'))) {
            return $back;
        }

        return route('work.index', ['date' => $job->work_date->toDateString()]);
    }

    private function authorizeEdit(Request $request, TechnicianJob $job): void
    {
        abort_unless($job->editableBy($request->user()), 403,
            'แก้ไข/ลบได้เฉพาะงานที่ตัวเองลงไว้และยังไม่ใช่งานย้อนหลัง');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?TechnicianJob $job = null): array
    {
        $canBackdate = $request->user()->canBackdateWork();

        $request->merge([
            'license_plate' => trim((string) $request->input('license_plate')) ?: null,
            'note' => trim((string) $request->input('note')) ?: null,
        ]);

        $data = $request->validate([
            'technician_id' => ['required', Rule::exists('technicians', 'id')
                ->where('brand_id', current_brand_id())
                // ช่างที่ลบไปแล้วเลือกใหม่ไม่ได้ แต่แก้งานเดิมของเขาได้
                ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $job?->technician_id))],
            'work_date' => array_values(array_filter([
                'required', 'date_format:Y-m-d', $canBackdate ? null : 'after_or_equal:today',
            ])),
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'job_type' => ['required', Rule::in(TechnicianJob::TYPES)],
            'license_plate' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'technician_id.required' => 'กรุณาเลือกช่าง',
            'technician_id.exists' => 'ไม่พบช่างที่เลือก',
            'work_date.required' => 'กรุณาเลือกวันที่',
            'work_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'work_date.after_or_equal' => 'ลงงานย้อนหลังไม่ได้ (ลงย้อนหลังได้เฉพาะ Admin)',
            'start_time.*' => 'กรุณาเลือกเวลาเริ่ม',
            'end_time.required' => 'กรุณาเลือกเวลาเสร็จ',
            'end_time.date_format' => 'กรุณาเลือกเวลาเสร็จ',
            'end_time.after' => 'เวลาเสร็จต้องหลังเวลาเริ่ม',
            'job_type.*' => 'กรุณาเลือกประเภทงาน',
            'license_plate.max' => 'ป้ายทะเบียนยาวเกิน 20 ตัวอักษร',
            'note.max' => 'หมายเหตุยาวเกิน 1,000 ตัวอักษร',
        ]);

        $clash = TechnicianJob::overlapping(
            (int) $data['technician_id'], $data['work_date'], $data['start_time'], $data['end_time'], $job?->id
        )->orderBy('start_time')->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'start_time' => 'ช่างคนนี้มีงาน '.$clash->job_type.' เวลา '.$clash->startLabel().'–'.$clash->endLabel()
                    .' น. อยู่แล้ว เวลาทับกันไม่ได้',
            ]);
        }

        return $data;
    }
}
