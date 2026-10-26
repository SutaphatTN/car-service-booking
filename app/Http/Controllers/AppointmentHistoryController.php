<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CallLog;
use App\Models\Title;
use App\Support\ServiceQueueImporter;
use App\Support\ServiceSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ประวัติการนัดหมาย — admin เท่านั้น (กำหนดไว้ที่ route)
 *
 * แก้ได้ทุกช่องรวมถึงงานที่ปิดไปแล้ว ไว้แก้กรณีกดผิด เช่นปิดงานผิดวัน ลงวันนัดผิด
 * ต่างจากหน้ารายการนัดหมายที่แก้ได้เฉพาะงานที่ยังเปิดอยู่ และไม่แตะสถานะ
 */
class AppointmentHistoryController extends Controller
{
    private const PER_PAGE = 20;

    /** ตัวเลือกบนแถบกรอง — ค่าตั้งต้นคือทั้งหมด เพราะหน้านี้ไว้ดูย้อนหลัง */
    private const STATUSES = ['all', 'done', 'declined', 'open'];

    /** สถานะการปิดคิวที่เลือกได้ในฟอร์มแก้ไข */
    public const CLOSE_STATES = [
        'open' => 'ยังไม่ปิด',
        'done' => 'เช็คแล้ว (ปิดงาน)',
        'declined' => 'ไม่เข้าเช็ค (ปิดคิว)',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? 'all';

        return view('history.index', [
            'search' => $search,
            'status' => $status,
            'statuses' => self::STATUSES,
            // ล่าสุดขึ้นก่อน — ส่วนใหญ่มาแก้งานที่เพิ่งกดผิด
            'appointments' => Appointment::with(['title'])
                ->withCount(['callLogs' => fn ($q) => $q->active()])
                ->withMax('callLogs', 'created_at')
                ->withStatus($status)
                ->search($search)
                ->orderByDesc('appointment_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['title', 'creator', 'brand', 'callLogs' => fn ($q) => $q->with('user')->orderBy('created_at')]);

        return view('history.edit', [
            'appointment' => $appointment,
            'titles' => Title::active()->get(),
            'closeStates' => self::CLOSE_STATES,
            'nextQueue' => $this->nextQueueOf($appointment),
        ]);
    }

    public function update(Request $request, Appointment $appointment, ServiceQueueImporter $importer): RedirectResponse
    {
        $fromSales = $appointment->isFromSales();
        $datetime = ['nullable', 'date_format:Y-m-d\TH:i'];

        $data = $request->validate([
            // ข้อมูลลูกค้า/รถของคิวจากฝ่ายขาย ถูกทับทุกรอบดึงข้อมูล จึงไม่รับมาแก้
            'title_id' => [Rule::excludeIf($fromSales), 'nullable', 'exists:titles,id'],
            'first_name' => [Rule::excludeIf($fromSales), 'required', 'string', 'max:100'],
            'last_name' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:100'],
            'phone' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:20'],
            'phone2' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:20'],
            'vin' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:30'],
            'model_name' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:100'],
            'submodel_name' => [Rule::excludeIf($fromSales), 'nullable', 'string', 'max:150'],

            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:1000'],

            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'confirmed_at' => $datetime,

            'recall_at' => ['nullable', 'date_format:Y-m-d'],
            'recall_reason' => ['nullable', 'required_with:recall_at', Rule::in(array_keys(Appointment::RECALL_REASONS))],
            'unreachable_count' => ['required', 'integer', 'min:0', 'max:999'],

            'close_state' => ['required', Rule::in(array_keys(self::CLOSE_STATES))],
            'serviced_at' => ['exclude_unless:close_state,done', 'required', 'date_format:Y-m-d'],
            'completed_at' => ['exclude_unless:close_state,done', 'required', 'date_format:Y-m-d\TH:i'],
            'declined_at' => ['exclude_unless:close_state,declined', 'required', 'date_format:Y-m-d\TH:i'],
            'declined_reason' => ['exclude_unless:close_state,declined', 'required', Rule::in(array_keys(Appointment::DECLINE_REASONS))],
            'declined_note' => ['exclude_unless:close_state,declined', 'nullable', 'string', 'max:200'],

            'recalc_next' => ['nullable', 'boolean'],
        ], [
            'first_name.required' => 'กรุณากรอกชื่อ',
            'mileage.required' => 'กรุณากรอกเลขไมล์',
            'appointment_date.required' => 'กรุณาเลือกวันนัด',
            'appointment_time.required' => 'กรุณาเลือกเวลานัด',
            'recall_reason.required_with' => 'เลือกวันโทรซ้ำแล้วต้องเลือกเหตุผลด้วย',
            'serviced_at.required' => 'ปิดงานแล้วต้องมีวันที่รถเข้าเช็คจริง',
            'completed_at.required' => 'ปิดงานแล้วต้องมีวันเวลาที่กดปิด',
            'declined_at.required' => 'ปิดคิวแล้วต้องมีวันเวลาที่ปิดคิว',
            'declined_reason.required' => 'กรุณาเลือกเหตุผลที่ไม่เข้าเช็ค',
        ]);

        $toTime = fn (?string $value) => $value ? Carbon::createFromFormat('Y-m-d\TH:i', $value) : null;
        $state = $data['close_state'];

        $changes = collect($data)->only([
            'title_id', 'first_name', 'last_name', 'phone', 'phone2', 'vin', 'model_name', 'submodel_name',
            'mileage', 'note', 'unreachable_count',
        ])->all();

        if (! $fromSales) {
            $changes['phone'] = preg_replace('/\D/', '', (string) ($data['phone'] ?? '')) ?: null;
            $changes['phone2'] = preg_replace('/\D/', '', (string) ($data['phone2'] ?? '')) ?: null;
        }

        $changes += [
            'appointment_at' => Carbon::parse($data['appointment_date'].' '.$data['appointment_time']),
            'confirmed_at' => $toTime($data['confirmed_at'] ?? null),
            'recall_at' => $data['recall_at'] ?? null,
            'recall_reason' => ($data['recall_at'] ?? null) ? $data['recall_reason'] : null,
            // เลื่อนวันโทรนับจาก unreachable_at — ลบวันโทรซ้ำก็ต้องล้างคู่กัน ไม่งั้นสถานะค้างเป็นเลื่อนโทร
            'unreachable_at' => ($data['recall_at'] ?? null) ? ($appointment->unreachable_at ?? now()) : null,
            'completed_at' => $state === 'done' ? $toTime($data['completed_at']) : null,
            'serviced_at' => $state === 'done' ? $data['serviced_at'] : null,
            'declined_at' => $state === 'declined' ? $toTime($data['declined_at']) : null,
            'declined_reason' => $state === 'declined' ? $data['declined_reason'] : null,
            'declined_note' => $state === 'declined' ? ($data['declined_note'] ?? null) : null,
        ];

        $appointment->update($changes);

        $message = 'แก้ไขประวัติของ '.$appointment->load('title')->fullName().' แล้ว';
        $message .= $this->syncNextQueue($appointment, $importer, (bool) ($data['recalc_next'] ?? false));

        return redirect()->route('history.edit', $appointment)->with('status', $message);
    }

    /** แก้ผลการโทรหนึ่งครั้ง — ไม่แตะสถานะของคิว (สถานะแก้ที่ฟอร์มด้านบน) */
    public function updateCall(Request $request, Appointment $appointment, CallLog $callLog): RedirectResponse
    {
        // error bag แยก — ฟอร์มผลการโทรมีหลายอัน ไม่ให้ไปขึ้นแดงที่ฟอร์มคิวด้านบน
        $data = $request->validateWithBag('call', [
            'created_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'result' => ['required', Rule::in(array_keys(CallLog::RESULTS))],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'booked_at' => ['nullable', 'required_if:result,confirmed', 'date_format:Y-m-d\TH:i'],
            'recall_at' => ['nullable', 'date_format:Y-m-d'],
            'declined_reason' => ['nullable', 'required_if:result,declined', Rule::in(array_keys(Appointment::DECLINE_REASONS))],
            'note' => ['nullable', 'string', 'max:200'],
            'cancelled' => ['nullable', 'boolean'],
        ], [
            'booked_at.required_if' => 'ผล "ได้นัด" ต้องมีวันเวลานัด',
            'declined_reason.required_if' => 'ผล "ไม่เข้าเช็ค" ต้องมีเหตุผล',
        ]);

        $result = $data['result'];

        // created_at ไม่อยู่ใน $fillable (ปกติเป็นเวลาที่กดบันทึก) — แก้ย้อนหลังได้เฉพาะหน้านี้
        $callLog->forceFill([
            'created_at' => Carbon::createFromFormat('Y-m-d\TH:i', $data['created_at']),
            'result' => $result,
            'due_date' => $data['due_date'],
            'booked_at' => $result === 'confirmed' ? Carbon::createFromFormat('Y-m-d\TH:i', $data['booked_at']) : null,
            'recall_at' => in_array($result, ['unreachable', 'not_ready'], true) ? ($data['recall_at'] ?? null) : null,
            'declined_reason' => $result === 'declined' ? $data['declined_reason'] : null,
            'note' => $data['note'] ?? null,
            // ติ๊กยกเลิกซ้ำไม่ทับเวลาที่ยกเลิกเดิม
            'cancelled_at' => ($data['cancelled'] ?? false) ? ($callLog->cancelled_at ?? now()) : null,
        ])->save();

        return redirect()->route('history.edit', $appointment)
            ->with('status', 'แก้ไขผลการโทรวันที่ '.thai_date($callLog->created_at, short: true).' แล้ว');
    }

    /** คิวครั้งถัดไปของรถคันเดียวกัน (ถ้ามี) */
    private function nextQueueOf(Appointment $appointment): ?Appointment
    {
        if (! $appointment->isFromSales() || $appointment->service_no === null) {
            return null;
        }

        return Appointment::where('salecar_id', $appointment->salecar_id)
            ->where('service_no', $appointment->service_no + 1)
            ->first();
    }

    /**
     * ปิดงานแล้วต้องมีคิวครั้งถัดไป — สร้างให้ถ้ายังไม่มี (เช่นเปลี่ยนจากยังไม่ปิดเป็นเช็คแล้ว)
     * ถ้ามีอยู่แล้วและขอให้คิดใหม่ ขยับวันครบกำหนดตามวันที่รถเข้าที่แก้ — เฉพาะคิวที่ยังไม่ได้โทรนัด
     */
    private function syncNextQueue(Appointment $appointment, ServiceQueueImporter $importer, bool $recalc): string
    {
        $next = $this->nextQueueOf($appointment);
        $continues = $appointment->completed_at !== null
            || ($appointment->declined_at !== null && $appointment->declined_reason === 'skip');

        if (! $continues) {
            return $next ? ' · คิวครั้งที่ '.$next->service_no.' ที่สร้างไว้แล้วยังอยู่ ลบได้จากหน้ารายการนัดหมาย (SA)' : '';
        }

        if ($next === null) {
            $created = $importer->createNextFor($appointment);

            return $created
                ? ' · สร้างคิวครั้งที่ '.$created->service_no.' ครบกำหนด '.thai_date($created->appointment_at, short: true).' ให้แล้ว'
                : '';
        }

        if (! $recalc) {
            return '';
        }

        if (! $next->needsCall()) {
            return ' · คิวครั้งที่ '.$next->service_no.' ยืนยันนัดหรือปิดไปแล้ว ไม่ได้ขยับวันให้ แก้ที่คิวนั้นแทน';
        }

        $base = $appointment->serviced_at ?? $appointment->completed_at ?? $appointment->declined_at;
        $due = ServiceSchedule::nextAfterService(
            $appointment->brand?->name ?? '', $appointment->model_id, Carbon::parse($base), (int) $appointment->service_no
        );

        if ($due === null) {
            return '';
        }

        $next->update([
            'appointment_at' => $due['due_date']->copy()->setTimeFrom($next->appointment_at),
        ]);

        return ' · ขยับคิวครั้งที่ '.$next->service_no.' เป็นครบกำหนด '.thai_date($next->appointment_at, short: true);
    }
}
