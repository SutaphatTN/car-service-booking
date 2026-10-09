<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\CallLog;
use App\Models\Title;
use App\Support\Schedule;
use App\Support\ServiceQueueImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    /** จำนวนรายการต่อหนึ่งหน้า */
    private const PER_PAGE = 10;

    /**
     * ตัวเลือกบนแถบกรองสถานะ เรียงตามลำดับที่แสดงบนหน้าจอ
     *
     * 'open' เป็นค่าตั้งต้น — งานที่ปิดแล้วไม่ถูกลบทิ้ง แค่ซ่อนไว้ ไม่งั้นจะกองอยู่
     * บนสุดของหน้าแรก (ตารางเรียงจากวันเก่าไปใหม่) จนดันงานที่ยังต้องทำตกไปหน้าหลัง
     */
    private const STATUSES = [
        'open', 'overdue', 'waiting', 'unreachable', 'not_ready', 'booked', 'declined', 'done', 'all',
    ];

    /** ตารางนัดหมาย เรียงตามวันและเวลา แบ่งเป็นหน้า ๆ */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ], [
            'status.in' => 'สถานะที่เลือกไม่ถูกต้อง',
        ]);

        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? 'open';

        return view('appointments.index', [
            'search' => $search,
            'status' => $status,
            // brand ใช้ตอนคิดวันครบกำหนดของคิวครั้งถัดไปในป็อปอัปปิดงาน ดึงมาทีเดียวกันยิงทีละแถว
            'appointments' => Appointment::with(['title', 'creator', 'brand'])
                ->withStatus($status)
                ->search($search)
                ->ordered()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            // จำนวนบนแถบกรองต้องนับภายใต้คำค้นเดียวกัน ไม่งั้นกดแล้วเจอตัวเลขไม่ตรง
            'statusCounts' => collect(self::STATUSES)->mapWithKeys(fn (string $key) => [
                $key => Appointment::withStatus($key)->search($search)->count(),
            ]),
            'titles' => Title::active()->get(),
            'timeSlots' => Schedule::timeSlots(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = Appointment::create([
            'title_id' => $request->validated('title_id'),
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'phone2' => $request->validated('phone2'),
            'vin' => $request->validated('vin'),
            'delivered_at' => $request->validated('delivered_at'),
            'mileage' => $request->validated('mileage'),
            'appointment_at' => $request->appointmentAt(),
            'note' => $request->validated('note'),
            'created_by' => $request->user()->id,
            // คีย์เองแปลว่าตกลงวันเวลากับลูกค้ามาแล้ว ไม่ต้องไปโผล่ในรายการต้องโทรหาอีก
            'confirmed_at' => now(),
        ]);

        return back()->with('status', 'บันทึกนัดหมายของ '.$appointment->load('title')->fullName().' แล้ว');
    }

    /**
     * รถเข้าศูนย์แล้วเช็คระยะเสร็จ — ปิดงาน บันทึกเลขไมล์จริง
     * แล้วระบบสร้างคิวเช็คระยะครั้งถัดไปให้เองจากตารางของรุ่นนั้น
     */
    public function complete(Request $request, Appointment $appointment, ServiceQueueImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'mileage.required' => 'กรุณากรอกเลขไมล์ที่วัดได้',
            'mileage.integer' => 'เลขไมล์ต้องเป็นตัวเลข',
        ]);

        if ($appointment->completed_at !== null) {
            return back()->with('status', 'นัดนี้ปิดงานไปแล้ว');
        }

        $appointment->update([
            'mileage' => $data['mileage'],
            'completed_at' => now(),
            'confirmed_at' => $appointment->confirmed_at ?? now(),
        ]);

        $next = $importer->createNextFor($appointment);

        $message = 'ปิดงานเช็คระยะของ '.$appointment->fullName().' แล้ว';

        if ($next) {
            $message .= ' · คิวครั้งที่ '.$next->service_no.' ครบกำหนด '
                .thai_date($next->appointment_at, short: true).' ระบบสร้างให้แล้ว';
        } elseif ($appointment->isFromSales()) {
            $message .= ' · ครบตารางเช็คระยะของรุ่นนี้แล้ว ไม่มีคิวถัดไป';
        }

        return back()->with('status', $message);
    }

    /** แก้ไขนัดหมาย — สิทธิ์และช่องที่แก้ได้ตรวจใน UpdateAppointmentRequest */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $data = collect($request->validated())
            ->except(['appointment_date', 'appointment_time'])
            ->put('appointment_at', $request->appointmentAt())
            ->all();

        $appointment->update($data);

        return back()->with('status', 'แก้ไขนัดหมายของ '.$appointment->load('title')->fullName().' แล้ว');
    }

    /**
     * ยกเลิกการยืนยันนัด — กดลงเวลานัดผิด (เช่นตั้งใจจะกดเลื่อนโทร) เอาคิวกลับไปรอโทรตามเดิม
     *
     * วันนัดกลับเป็นวันครบกำหนดเดิมตามตารางเช็คระยะ และผล "ได้นัด" ในรายงานการโทรถูกยกเลิก
     * ใช้ได้เฉพาะคิวจากฝ่ายขาย — นัดที่คีย์เองไม่เคยอยู่ในรายการต้องโทรหา
     */
    public function unconfirm(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->canEditAppointments(), 403);

        if (! $appointment->isFromSales() || $appointment->confirmed_at === null
            || $appointment->completed_at !== null || $appointment->declined_at !== null) {
            return back()->withErrors(
                ['unconfirm' => 'นัดนี้ยกเลิกการยืนยันไม่ได้ (ต้องเป็นคิวจากฝ่ายขายที่ยืนยันแล้วและยังไม่ปิดงาน)'], 'edit'
            );
        }

        // ต้องหาวันครบกำหนดเดิมก่อนล้าง confirmed_at — serviceDueDate() ดูจากสถานะนี้
        $dueDate = $appointment->serviceDueDate();
        [$hour, $minute] = explode(':', ServiceQueueImporter::DEFAULT_TIME);

        CallLog::cancelLatest($appointment, ['confirmed']);

        $appointment->update([
            'confirmed_at' => null,
            'appointment_at' => $dueDate->copy()->setTime((int) $hour, (int) $minute),
        ]);

        return back()->with('status', 'ยกเลิกการยืนยันนัดของ '.$appointment->load('title')->fullName()
            .' แล้ว — คิวกลับไปอยู่ในรายการต้องโทรหา (ครบกำหนด '.thai_date($dueDate, short: true).')');
    }

    /** ลบได้เฉพาะ sa (กำหนดไว้ที่ route) */
    public function destroy(Appointment $appointment): RedirectResponse
    {
        $name = $appointment->load('title')->fullName();
        $appointment->delete();

        return back()->with('status', 'ลบนัดหมายของ '.$name.' แล้ว');
    }
}
