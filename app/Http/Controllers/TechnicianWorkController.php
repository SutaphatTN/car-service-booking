<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\TechnicianJob;
use App\Support\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ปริมาณงานช่าง — ปฏิทินรายวัน (ช่างเรียงเป็นแถว เวลาเป็นแกนนอน แบบตารางเรียน) + ลง/แก้/ลบงาน
 *
 * กันแอบลงงานเพิ่มเพื่อค่าคอม: ลงงานย้อนหลังได้เฉพาะ admin
 * คนอื่นแก้/ลบได้เฉพาะงานที่ตัวเองลงและยังไม่ใช่งานย้อนหลัง (ดู TechnicianJob::editableBy)
 */
class TechnicianWorkController extends Controller
{
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

        // ช่วงเวลาบนแกนนอน — ปกติตามเวลาทำการ งานไหนเลยออกไปก็ขยายเป็นชั่วโมงเต็มให้ครอบ
        $start = min(WorkSchedule::DAY_START, intdiv((int) $jobs->min(fn ($job) => $job->startMinutes()) ?: WorkSchedule::DAY_START, 60) * 60);
        $end = max(WorkSchedule::DAY_END, (int) ceil(((int) $jobs->max(fn ($job) => $job->endMinutes())) / 60) * 60);

        $user = $request->user();

        return view('work.index', [
            'date' => $date,
            'jobs' => $jobs->groupBy('technician_id'),
            'technicians' => $technicians,
            'activeTechnicians' => $technicians->reject(fn (Technician $t) => $t->trashed()),
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'summary' => [
                'jobs' => $jobs->count(),
                'minutes' => $jobs->sum(fn ($job) => $job->durationMinutes()),
                'types' => collect(TechnicianJob::TYPES)->mapWithKeys(
                    fn (string $type) => [$type => $jobs->where('job_type', $type)->count()]
                ),
            ],
            'canAdd' => $user->canBackdateWork() || ! $date->lt(today()),
            'canBackdate' => $user->canBackdateWork(),
            'timeOptions' => WorkSchedule::timeOptions(),
            'openAdd' => $request->boolean('add'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $job = TechnicianJob::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('work.index', ['date' => $job->work_date->toDateString()])
            ->with('status', 'ลงงาน '.$job->job_type.' ของ '.$job->technician->name
                .' เวลา '.$job->startLabel().'–'.$job->endLabel().' น. แล้ว');
    }

    public function update(Request $request, TechnicianJob $job): RedirectResponse
    {
        $this->authorizeEdit($request, $job);

        $job->update($this->validated($request, $job));

        return redirect()->route('work.index', ['date' => $job->work_date->toDateString()])
            ->with('status', 'แก้ไขงานของ '.$job->technician->name.' แล้ว');
    }

    public function destroy(Request $request, TechnicianJob $job): RedirectResponse
    {
        $this->authorizeEdit($request, $job);

        $job->delete();

        return redirect()->route('work.index', ['date' => $job->work_date->toDateString()])
            ->with('status', 'ลบงาน '.$job->job_type.' ของ '.$job->technician->name.' แล้ว');
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
