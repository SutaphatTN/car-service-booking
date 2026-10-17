<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Support\CarModels;
use App\Support\DeliveredCars;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    /** ค้น VIN ในระบบขายครั้งเดียวต่อคำขอ (null ก็จำไว้ว่าค้นแล้วไม่เจอ) */
    private bool $salesCarLoaded = false;

    private ?object $salesCar = null;

    private ?object $stockModel = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title_id' => ['required', 'integer', 'exists:titles,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{9}$/'],
            'phone2' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            // VIN ตามมาตรฐาน ISO 3779 — 17 หลัก ไม่มีตัว I O Q (กันสับสนกับเลข 1 กับ 0)
            'vin' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'delivered_at' => ['nullable', 'date_format:Y-m-d'],
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
            // รุ่นรถ — ใช้เฉพาะเมื่อหา VIN ในระบบขายไม่เจอ (ตรวจกับรายชื่อรุ่นจริงใน after())
            'model_id' => ['nullable', 'integer'],
            'submodel_name' => ['nullable', 'string', 'max:150'],
            // รถมีคิวจากฝ่ายขายอยู่แล้ว: id ของคิว = ลงนัดในคิวนั้น, 'new' = สร้างนัดใหม่แยก
            'use_queue' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** ตรวจรุ่นรถกับคิวเดิม — ต้องรู้ VIN ที่ผ่านการตรวจแล้วก่อน */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->checksCar() || $validator->errors()->has('vin')) {
                return;
            }

            if ($this->salesCar() !== null) {
                $queue = $this->asksAboutQueue() ? $this->openQueue() : null;

                if ($queue && ! in_array($this->input('use_queue'), [(string) $queue->id, 'new'], true)) {
                    $validator->errors()->add('use_queue',
                        'รถคันนี้มีคิวเช็คระยะจากระบบขายอยู่แล้ว กรุณาเลือกว่าจะลงนัดในคิวเดิมหรือสร้างนัดใหม่');
                }

                return;
            }

            // ไม่มีใบขาย แต่มีใบสั่งรถเข้าสต็อก — รู้รุ่นแล้ว ไม่ต้องเลือก
            if ($this->stockModel() !== null) {
                return;
            }

            $model = $this->chosenModel();

            if ($model === null) {
                $validator->errors()->add('model_id', 'ไม่พบ VIN นี้ในระบบขาย กรุณาเลือกรุ่นรถ');
            } elseif ($this->input('submodel_name') !== null
                && ! in_array($this->input('submodel_name'), $model['submodels'], true)) {
                $validator->errors()->add('submodel_name', 'ไม่พบรุ่นย่อยที่เลือกในรุ่นนี้');
            }
        }];
    }

    /** ตรวจ/บันทึกข้อมูลรถหรือไม่ — แก้คิวจากฝ่ายขายไม่ได้แตะข้อมูลรถ */
    protected function checksCar(): bool
    {
        return true;
    }

    /** ถามเรื่องคิวเดิมหรือไม่ — ถามเฉพาะตอนเพิ่มนัด */
    protected function asksAboutQueue(): bool
    {
        return true;
    }

    /** รถในระบบขายที่ VIN ตรงกัน (เฉพาะยี่ห้อที่กำลังทำงานอยู่) */
    public function salesCar(): ?object
    {
        if (! $this->salesCarLoaded) {
            $this->salesCar = DeliveredCars::findByVin($this->input('vin'), [(string) current_brand()?->name]);
            $this->salesCarLoaded = true;
        }

        return $this->salesCar;
    }

    /** รุ่นจากใบสั่งรถเข้าสต็อก — ใช้เมื่อไม่เจอใบขาย */
    public function stockModel(): ?object
    {
        return $this->salesCar() === null
            ? $this->stockModel ??= CarModels::ofStockVin($this->input('vin'), current_brand()?->name)
            : null;
    }

    /** คิวที่ยังไม่ปิดของรถคันนี้ */
    public function openQueue(): ?Appointment
    {
        $car = $this->salesCar();

        return $car ? Appointment::openQueueOf((int) $car->salecar_id) : null;
    }

    /** คิวที่เลือกให้ลงนัด — null = สร้างนัดใหม่ */
    public function chosenQueue(): ?Appointment
    {
        $queue = $this->asksAboutQueue() ? $this->openQueue() : null;

        return $queue && $this->validated('use_queue') === (string) $queue->id ? $queue : null;
    }

    /** @return array{id:int, name:string, submodels:list<string>}|null */
    private function chosenModel(): ?array
    {
        $id = (int) $this->input('model_id');

        return $id > 0
            ? CarModels::forBrand(current_brand()?->name)->firstWhere('id', $id)
            : null;
    }

    /**
     * ข้อมูลรถที่จะบันทึก — เจอในระบบขายใช้ของระบบขาย ไม่เจอใช้รุ่นที่เลือกเอง
     * วันส่งมอบที่กรอกมาเองมาก่อน ถ้าเว้นว่างค่อยเติมจากระบบขาย
     *
     * @return array{model_id:?int, model_name:?string, submodel_name:?string, delivered_at:mixed}
     */
    public function carFields(): array
    {
        $car = $this->salesCar();

        if ($car !== null) {
            return [
                'model_id' => $car->model_id,
                'model_name' => $car->model,
                'submodel_name' => $car->submodel,
                'delivered_at' => $this->validated('delivered_at') ?? $car->delivered_at,
            ];
        }

        if ($stock = $this->stockModel()) {
            return [
                'model_id' => $stock->model_id,
                'model_name' => $stock->model,
                'submodel_name' => $stock->submodel,
                'delivered_at' => $this->validated('delivered_at'),
            ];
        }

        $model = $this->chosenModel();

        return [
            'model_id' => $model['id'] ?? null,
            'model_name' => $model['name'] ?? null,
            'submodel_name' => $this->validated('submodel_name'),
            'delivered_at' => $this->validated('delivered_at'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title_id.required' => 'กรุณาเลือกคำนำหน้า',
            'title_id.exists' => 'ไม่พบคำนำหน้าที่เลือก',
            'first_name.required' => 'กรุณากรอกชื่อ',
            'last_name.required' => 'กรุณากรอกนามสกุล',
            'phone.required' => 'กรุณากรอกเบอร์โทร',
            'phone.regex' => 'เบอร์โทรต้องขึ้นต้นด้วย 0 และมี 10 หลัก',
            'phone2.regex' => 'เบอร์สำรองต้องขึ้นต้นด้วย 0 และมี 10 หลัก',
            'vin.required' => 'กรุณากรอกเลขตัวถัง (VIN)',
            'vin.size' => 'เลขตัวถัง (VIN) ต้องมี 17 หลัก',
            'vin.regex' => 'เลขตัวถัง (VIN) ใช้ได้เฉพาะ A-Z และ 0-9 ยกเว้นตัว I O Q',
            'delivered_at.date_format' => 'รูปแบบวันที่ส่งมอบไม่ถูกต้อง',
            'mileage.required' => 'กรุณากรอกเลขไมล์',
            'mileage.integer' => 'เลขไมล์ต้องเป็นตัวเลข',
            'appointment_date.required' => 'กรุณาเลือกวันที่นัด',
            'appointment_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'appointment_time.required' => 'กรุณาเลือกเวลานัด',
            'appointment_time.date_format' => 'รูปแบบเวลาไม่ถูกต้อง',
            'note.max' => 'หมายเหตุยาวเกิน 1,000 ตัวอักษร',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
            'phone2' => preg_replace('/\D/', '', (string) $this->input('phone2')) ?: null,
            // VIN พิมพ์เล็กหรือมีเว้นวรรค/ขีดคั่นมาก็รับได้ เก็บเป็นตัวพิมพ์ใหญ่ล้วน
            'vin' => strtoupper(str_replace([' ', '-'], '', trim((string) $this->input('vin')))) ?: null,
            'delivered_at' => trim((string) $this->input('delivered_at')) ?: null,
            'mileage' => is_string($this->input('mileage'))
                ? str_replace([',', ' '], '', $this->input('mileage'))
                : $this->input('mileage'),
            'appointment_time' => substr((string) $this->input('appointment_time'), 0, 5),
            'note' => trim((string) $this->input('note')) ?: null,
            'model_id' => $this->input('model_id') ?: null,
            'submodel_name' => trim((string) $this->input('submodel_name')) ?: null,
            'use_queue' => $this->input('use_queue') ?: null,
        ]);
    }

    /** รวมวันที่กับเวลาเป็นคอลัมน์เดียว appointment_at */
    public function appointmentAt(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->validated('appointment_date').' '.$this->validated('appointment_time')
        )->startOfMinute();
    }
}
