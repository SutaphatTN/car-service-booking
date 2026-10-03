<?php

namespace App\Support;

/** ช่วงเวลาของปฏิทินงานช่าง — แก้ค่าตรงนี้จุดเดียว (หน่วยเป็นนาทีนับจากเที่ยงคืน) */
class WorkSchedule
{
    /** ปฏิทินแสดงช่วงนี้เสมอ ถ้ามีงานเลยออกไปจะขยายให้เอง */
    public const DAY_START = 8 * 60;

    public const DAY_END = 17 * 60;

    /** ตัวเลือกเวลาในฟอร์มลงงาน — กว้างกว่าเวลาทำการ เผื่อทำงานเช้า/ล่วงเวลา */
    public const PICK_START = 6 * 60;

    public const PICK_END = 22 * 60;

    public const PICK_STEP = 15;

    /** @return list<string> เวลาแบบ 24 ชั่วโมง เช่น "08:15" */
    public static function timeOptions(): array
    {
        $options = [];

        for ($minutes = self::PICK_START; $minutes <= self::PICK_END; $minutes += self::PICK_STEP) {
            $options[] = self::label($minutes);
        }

        return $options;
    }

    public static function label(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
