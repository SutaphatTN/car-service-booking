<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\CallLog;
use App\Models\Title;
use App\Support\CarModels;
use App\Support\DeliveredCars;
use App\Support\Schedule;
use App\Support\ServiceQueueImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'carModels' => CarModels::forBrand(current_brand()?->name),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        if ($queue = $request->chosenQueue()) {
            return $this->bookQueue($request, $queue);
        }

        $appointment = Appointment::create($request->carFields() + [
            'title_id' => $request->validated('title_id'),
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'phone2' => $request->validated('phone2'),
            'vin' => $request->validated('vin'),
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
     * ลงนัดในคิวเดิมของรถคันนี้แทนการสร้างนัดใหม่ — ทำเหมือนกดยืนยันนัดในหน้าต้องโทรหา
     * ปิดงานแล้วระบบจึงสร้างคิวครั้งถัดไปให้ได้ และไม่เหลือคิวเก่าค้างให้โทรซ้ำ
     *
     * ชื่อ เบอร์ และข้อมูลรถในคิวเป็นของฝ่ายขาย ไม่เอาที่กรอกในฟอร์มไปทับ
     */
    private function bookQueue(StoreAppointmentRequest $request, Appointment $queue): RedirectResponse
    {
        $bookedAt = $request->appointmentAt();

        // บันทึกผล "ได้นัด" เฉพาะคิวที่ยังไม่เคยยืนยัน — ยกเลิกการยืนยันต้องใช้หาวันครบกำหนดเดิม
        if ($queue->confirmed_at === null) {
            CallLog::record($queue, 'confirmed', ['booked_at' => $bookedAt]);
        }

        $queue->update([
            'appointment_at' => $bookedAt,
            'mileage' => $request->validated('mileage'),
            'note' => $request->validated('note') ?? $queue->note,
            'confirmed_at' => now(),
            'unreachable_at' => null,
            'recall_at' => null,
            'unreachable_count' => 0,
        ]);

        return back()->with('status', 'ลงนัดในคิวเช็คระยะครั้งที่ '.$queue->service_no
            .' ของ '.$queue->load('title')->fullName().' แล้ว');
    }

    /**
     * ค้น VIN ในระบบขายระหว่างกรอกฟอร์ม — เจอแล้วเติมรุ่นให้เอง และบอกว่ามีคิวค้างอยู่ไหม
     * (ตอนบันทึกจริงตรวจซ้ำฝั่งเซิร์ฟเวอร์อีกรอบ ค่าที่ได้จากตรงนี้ใช้แสดงผลอย่างเดียว)
     */
    public function lookupVin(Request $request): JsonResponse
    {
        $car = DeliveredCars::findByVin((string) $request->query('vin'), [(string) current_brand()?->name]);

        if ($car === null) {
            $stock = CarModels::ofStockVin((string) $request->query('vin'), current_brand()?->name);

            return response()->json(['found' => false, 'stock' => $stock ? [
                'model_id' => $stock->model_id,
                'model' => $stock->model,
                'submodel' => $stock->submodel,
            ] : null]);
        }

        $queue = Appointment::openQueueOf((int) $car->salecar_id);
        $title = Title::active()->get()->first(fn (Title $t) => Title::matchKey($t->name) === Title::matchKey($car->title));

        return response()->json([
            'found' => true,
            'car' => [
                'model_id' => $car->model_id,
                'model' => $car->model,
                'submodel' => $car->submodel,
                'delivered_at' => $car->delivered_at ? Carbon::parse($car->delivered_at)->toDateString() : null,
                'title_id' => $title?->id,
                'first_name' => trim((string) $car->first_name),
                'last_name' => trim((string) $car->last_name),
                'phone' => phone_format(DeliveredCars::normalisePhone($car->phone)),
                'phone2' => phone_format(DeliveredCars::normalisePhone($car->phone2)),
            ],
            'queue' => $queue ? [
                'id' => $queue->id,
                'service_no' => $queue->service_no,
                'due' => thai_date($queue->serviceDueDate(), short: true),
                'status' => $queue->status(),
            ] : null,
        ]);
    }

    /**
     * รถเข้าศูนย์แล้วเช็คระยะเสร็จ — ปิดงาน บันทึกเลขไมล์จริง
     * แล้วระบบสร้างคิวเช็คระยะครั้งถัดไปให้เองจากตารางของรุ่นนั้น
     *
     * เก็บสองวัน: completed_at = เวลาที่กดปิดงานจริง, serviced_at = วันที่รถเข้าที่ผู้ใช้เลือก
     * (กดปิดช้า/ปิดย้อนหลังได้) คิวครั้งถัดไปนับจาก serviced_at
     */
    public function complete(Request $request, Appointment $appointment, ServiceQueueImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'serviced_date' => [
                'required', 'date_format:Y-m-d', 'before_or_equal:today',
                'after_or_equal:'.$appointment->appointment_at->toDateString(),
            ],
        ], [
            'mileage.required' => 'กรุณากรอกเลขไมล์ที่วัดได้',
            'mileage.integer' => 'เลขไมล์ต้องเป็นตัวเลข',
            'serviced_date.required' => 'กรุณาเลือกวันที่รถเข้าเช็คจริง',
            'serviced_date.date_format' => 'รูปแบบวันที่รถเข้าไม่ถูกต้อง',
            'serviced_date.before_or_equal' => 'วันที่รถเข้าต้องไม่เกินวันนี้',
            'serviced_date.after_or_equal' => 'วันที่รถเข้าต้องไม่ก่อนวันนัด หากลูกค้าเข้าก่อนให้แก้วันนัดก่อน',
        ]);

        if ($appointment->completed_at !== null) {
            return back()->with('status', 'นัดนี้ปิดงานไปแล้ว');
        }

        if (! $appointment->canComplete()) {
            return back()->with('status', 'ยังไม่ถึงวันนัดของ '.$appointment->fullName()
                .' — ปิดงานได้ตั้งแต่ '.thai_date($appointment->appointment_at, short: true).' หากลูกค้าเข้าก่อนให้แก้วันนัดก่อน');
        }

        $appointment->update([
            'mileage' => $data['mileage'],
            'completed_at' => now(),
            'serviced_at' => $data['serviced_date'],
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
            ->except(['appointment_date', 'appointment_time', 'model_id', 'submodel_name'])
            ->put('appointment_at', $request->appointmentAt())
            ->all();

        if (! $appointment->isFromSales()) {
            $data = $request->carFields() + $data;
        }

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
