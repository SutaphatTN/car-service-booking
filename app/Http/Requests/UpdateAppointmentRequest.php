<?php

namespace App\Http\Requests;

/**
 * แก้ไขนัดหมาย — เฉพาะผู้ใช้ที่ได้รับสิทธิ์ (User::canEditAppointments)
 * หรือ sa เลื่อนวันเวลานัดที่ยืนยันแล้ว (User::canReschedule)
 *
 * คิวที่ดึงมาจากฝ่ายขาย แก้ได้แค่วันเวลานัด เลขไมล์ และหมายเหตุ
 * ข้อมูลลูกค้า/รถเป็นของฝ่ายขาย แก้ตรงนี้ไปรอบ import ถัดไปก็ถูกทับกลับอยู่ดี
 */
class UpdateAppointmentRequest extends StoreAppointmentRequest
{
    /** ใช้ error bag แยก — ไม่งั้นกรอกผิดแล้วป็อปอัป "เพิ่มนัดหมาย" จะเด้งขึ้นมาแทน */
    protected $errorBag = 'edit';

    public function authorize(): bool
    {
        return (bool) $this->user()?->canEditAppointment($this->route('appointment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->route('appointment')->isFromSales()) {
            return array_intersect_key($rules, array_flip([
                'mileage', 'appointment_date', 'appointment_time', 'note',
            ]));
        }

        // คิวเดิมถามเฉพาะตอนเพิ่มนัด — แก้นัดที่มีอยู่แล้วไม่ย้ายไปคิวอื่น
        unset($rules['use_queue']);

        return $rules;
    }

    protected function checksCar(): bool
    {
        return ! $this->route('appointment')->isFromSales();
    }

    protected function asksAboutQueue(): bool
    {
        return false;
    }
}
