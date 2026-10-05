<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAppointmentRequest extends FormRequest
{
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
