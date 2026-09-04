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
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
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
            'mileage.required' => 'กรุณากรอกเลขไมล์',
            'mileage.integer' => 'เลขไมล์ต้องเป็นตัวเลข',
            'appointment_date.required' => 'กรุณาเลือกวันที่นัด',
            'appointment_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'appointment_time.required' => 'กรุณาเลือกเวลานัด',
            'appointment_time.date_format' => 'รูปแบบเวลาไม่ถูกต้อง',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
            'mileage' => is_string($this->input('mileage'))
                ? str_replace([',', ' '], '', $this->input('mileage'))
                : $this->input('mileage'),
            'appointment_time' => substr((string) $this->input('appointment_time'), 0, 5),
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
