<?php

namespace App\Http\Requests;

/**
 * แก้ไขนัดหมาย — เฉพาะผู้ใช้ที่ได้รับสิทธิ์ (User::canEditAppointments)
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
        return (bool) $this->user()?->canEditAppointments();
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

        return $rules;
    }
}
