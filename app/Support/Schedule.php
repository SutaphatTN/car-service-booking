<?php

namespace App\Support;

/** เวลาทำการของศูนย์บริการ — แก้ค่าตรงนี้จุดเดียว */
class Schedule
{
    /** เวลาเปิด (นาทีนับจากเที่ยงคืน) */
    public const OPEN_TIME = 8 * 60;

    /** เวลาปิด */
    public const CLOSE_TIME = 16 * 60;

    /** ระยะห่างของแต่ละช่วงนัด (นาที) */
    public const SLOT_MINUTES = 30;

    /**
     * ตัวเลือกเวลานัดแบบ 24 ชั่วโมง แยกกลุ่มเช้า/บ่าย
     * เพื่อไม่ให้ผู้ใช้สับสนกับ AM/PM ของช่องกรอกเวลาในเบราว์เซอร์
     *
     * @return array<string, list<string>>
     */
    public static function timeSlots(): array
    {
        $slots = ['ช่วงเช้า' => [], 'ช่วงบ่าย' => []];

        for ($minutes = self::OPEN_TIME; $minutes <= self::CLOSE_TIME; $minutes += self::SLOT_MINUTES) {
            $group = $minutes < 12 * 60 ? 'ช่วงเช้า' : 'ช่วงบ่าย';
            $slots[$group][] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return $slots;
    }
}
