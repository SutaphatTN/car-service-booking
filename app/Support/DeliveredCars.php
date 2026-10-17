<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * อ่านรถที่ส่งมอบแล้วจากฐานข้อมูลฝ่ายขาย (connection 'sales') — อ่านอย่างเดียว
 *
 * เงื่อนไขที่ถือว่า "ส่งมอบแล้ว" คือ salecars.con_status = 5
 * (ใช้ค่านี้แทนการเช็ค DeliveryDate เพราะฝ่ายขายยืนยันว่าสถานะนี้ข้อมูลครบ)
 */
class DeliveredCars
{
    /** สถานะ "ส่งมอบแล้ว" ในตาราง salecars */
    public const DELIVERED_STATUS = 5;

    /**
     * @param  list<string>  $brands  ชื่อยี่ห้อที่ต้องการ เช่น ['GWM', 'Wuling']
     * @return Collection<int, object>
     */
    public static function all(array $brands): Collection
    {
        return self::query($brands)->orderBy('s.DeliveryDate')->get();
    }

    /**
     * รถคันที่ VIN ตรงกัน — ใช้เติมรุ่นให้นัดที่เจ้าหน้าที่คีย์เอง
     *
     * VIN ฝั่งขายมีเว้นวรรคปน จึงตัดออกก่อนเทียบ ถ้าเจอหลายใบ (ไม่ควรมีหลังกรองใบที่ลบแล้ว)
     * เอาใบที่ส่งมอบล่าสุด
     *
     * @param  list<string>  $brands
     */
    public static function findByVin(?string $vin, array $brands): ?object
    {
        $vin = self::normaliseVin($vin);

        if ($vin === null) {
            return null;
        }

        return self::query($brands)
            ->whereRaw("UPPER(REPLACE(o.vin_number, ' ', '')) = ?", [$vin])
            ->orderByDesc('s.DeliveryDate')
            ->first();
    }

    /** @param  list<string>  $brands */
    private static function query(array $brands): Builder
    {
        return DB::connection('sales')
            ->table('salecars as s')
            ->join('tb_brand as b', 'b.id', '=', 's.brand')
            ->leftJoin('tb_carmodels as m', 'm.id', '=', 's.model_id')
            ->leftJoin('tb_subcarmodels as sm', 'sm.id', '=', 's.subModel_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.CusID')
            ->leftJoin('tb_prefixname as p', 'p.id', '=', 'c.PrefixName')
            ->leftJoin('car_order as o', 'o.id', '=', 's.CarOrderID')
            ->where('s.con_status', self::DELIVERED_STATUS)
            ->whereIn('b.name', $brands)
            // ใบที่ถูกลบทิ้งแล้ว ไม่เอา — เป็นที่มาของ VIN ซ้ำ (ใบเก่ากับใบที่ออกใหม่แทน)
            ->whereNull('s.deleted_at')
            // con_status = 5 แต่ไม่มีวันส่งมอบ คือใบที่ข้อมูลถูกลบไปแล้ว คำนวณระยะไม่ได้
            ->whereNotNull('s.DeliveryDate')
            ->select([
                's.id as salecar_id',
                'b.name as brand',
                'm.id as model_id',
                'm.Name_TH as model',
                // รุ่นย่อย — Mitsubishi เก็บรหัสไว้ที่ name ชื่ออ่านได้อยู่ที่ detail ส่วน GWM/Wuling ใช้ name (detail ว่าง)
                DB::raw("COALESCE(NULLIF(TRIM(sm.detail), ''), NULLIF(TRIM(sm.name), '')) as submodel"),
                's.DeliveryDate as delivered_at',
                'p.Name_TH as title',
                'c.FirstName as first_name',
                'c.LastName as last_name',
                'c.Mobilephone1 as phone',
                'c.Mobilephone2 as phone2',
                'o.vin_number as vin',
            ]);
    }

    /**
     * สั้นกว่านี้เป็นเบอร์โทรไม่ได้ — ช่องเบอร์สำรองของฝ่ายขายมีที่กรอก "0" หรือ "-"
     * ไว้แทนคำว่า "ไม่มี" อยู่หลายราย ถือเป็นค่าว่าง
     */
    private const MIN_PHONE_DIGITS = 9;

    /**
     * เบอร์โทรในฐานข้อมูลฝ่ายขายมีทั้งแบบมีขีดและเว้นวรรค เก็บของเราเป็นตัวเลขล้วน
     *
     * เบอร์ที่ "ผิดรูปแบบแต่เป็นเบอร์" เช่น 820099777 (ขาด 0 นำหน้า) เก็บไว้ตามจริง
     * ไม่เดาเติมให้ เพราะถ้าเดาผิดจะกลายเป็นโทรหาคนอื่น
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= self::MIN_PHONE_DIGITS ? $digits : null;
    }

    /** VIN ของฝ่ายขายบางแถวมีเว้นวรรคปน */
    public static function normaliseVin(?string $vin): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $vin));

        return $clean !== '' ? $clean : null;
    }
}
