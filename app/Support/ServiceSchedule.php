<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * ตารางเช็คระยะของรถแต่ละรุ่น — นับจาก "วันที่ส่งมอบจริง"
 *
 * ตัวเลขมาจากตารางเช็คระยะของผู้ผลิต แก้ที่นี่ที่เดียวเมื่อมีรุ่นใหม่
 * อ้างอิงด้วย "id รุ่นหลัก" ของฐานข้อมูลฝ่ายขาย (new_cars.tb_carmodels.id)
 *
 * ระยะทางเป็นตัวเลขอ้างอิงจากตาราง ใช้บอกว่าเป็นการเช็คครั้งที่เท่าไร
 * ไม่ใช่เลขไมล์จริงของรถ (เลขไมล์จริงกรอกตอนรถเข้าศูนย์)
 */
class ServiceSchedule
{
    /** ตารางแบบระบุครบทุกครั้ง — key = id รุ่นหลัก */
    private const FIXED = [
        // Wuling รถตู้ Porta EV — ครั้งแรก 3 เดือน จากนั้นทุก 6 เดือน
        19 => [
            'months' => [3, 9, 15, 21, 27, 33, 39, 45, 51, 57, 63],
            'mileage' => [5000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000],
        ],
        // Wuling BINGUO EV
        13 => [
            'months' => [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66],
            'mileage' => [5000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000],
        ],
        // Wuling Starlight Darion EV
        12 => [
            'months' => [6, 12, 18, 24, 30, 36, 42, 48, 54, 60],
            'mileage' => [5000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000],
        ],
    ];

    /** รุ่น GWM ตระกูล Ora — เช็คทุก 6 เดือน ไม่มีกำหนดจบ */
    private const GWM_ORA_MODELS = [17, 18, 22, 23];

    private const GWM_ORA_INTERVAL_MONTHS = 6;

    /** GWM รุ่นอื่น — เช็คทุก 12 เดือน ไม่มีกำหนดจบ */
    private const GWM_DEFAULT_INTERVAL_MONTHS = 12;

    /**
     * การเช็คระยะครั้งที่ $serviceNo (เริ่มที่ 1) ของรุ่นนี้
     *
     * @return array{service_no:int, months:int, due_date:Carbon, mileage:?int}|null
     *                                                                               null = ครบตารางแล้ว ไม่มีครั้งถัดไป
     */
    public static function serviceFor(string $brand, ?int $modelId, Carbon $deliveredAt, int $serviceNo): ?array
    {
        if ($serviceNo < 1) {
            return null;
        }

        $months = self::monthsFor($brand, $modelId, $serviceNo);

        if ($months === null) {
            return null;
        }

        return [
            'service_no' => $serviceNo,
            'months' => $months,
            // addMonthsNoOverflow กันวันที่ 31 เด้งข้ามเดือน เช่น 31 ม.ค. + 1 เดือน
            'due_date' => $deliveredAt->copy()->startOfDay()->addMonthsNoOverflow($months),
            'mileage' => self::mileageFor($modelId, $serviceNo),
        ];
    }

    /** จำนวนเดือนนับจากวันส่งมอบของการเช็คครั้งที่ $serviceNo — null = จบตารางแล้ว */
    public static function monthsFor(string $brand, ?int $modelId, int $serviceNo): ?int
    {
        if (isset(self::FIXED[$modelId])) {
            return self::FIXED[$modelId]['months'][$serviceNo - 1] ?? null;
        }

        // GWM ไม่มีตารางจบ คูณระยะห่างไปเรื่อย ๆ จนกว่าจะสั่งหยุดเอง
        if (self::isGwm($brand)) {
            return $serviceNo * (in_array($modelId, self::GWM_ORA_MODELS, true)
                ? self::GWM_ORA_INTERVAL_MONTHS
                : self::GWM_DEFAULT_INTERVAL_MONTHS);
        }

        return null;
    }

    /** ระยะทางอ้างอิงของการเช็คครั้งนั้น — null เมื่อรุ่นนั้นไม่มีตารางระยะทาง */
    public static function mileageFor(?int $modelId, int $serviceNo): ?int
    {
        return self::FIXED[$modelId]['mileage'][$serviceNo - 1] ?? null;
    }

    /** รุ่นนี้มีตารางเช็คระยะให้คำนวณหรือเปล่า */
    public static function supports(string $brand, ?int $modelId): bool
    {
        return isset(self::FIXED[$modelId]) || self::isGwm($brand);
    }

    /** จำนวนครั้งทั้งหมดในตาราง — null = ไม่มีกำหนดจบ (GWM) */
    public static function totalServices(string $brand, ?int $modelId): ?int
    {
        return isset(self::FIXED[$modelId]) ? count(self::FIXED[$modelId]['months']) : null;
    }

    private static function isGwm(string $brand): bool
    {
        return strcasecmp($brand, 'GWM') === 0;
    }
}
