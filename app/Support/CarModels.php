<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * รายชื่อรุ่นหลัก/รุ่นย่อยจากฐานข้อมูลฝ่ายขาย (connection 'sales') — อ่านอย่างเดียว
 *
 * ใช้เป็นตัวเลือกในฟอร์มนัดที่คีย์เอง เมื่อหา VIN ในระบบขายไม่เจอ (เช่นรถซื้อจากที่อื่น)
 * ใช้ชื่อชุดเดียวกับที่ระบบดึงมาให้ คิวทั้งสองแบบจะได้ค้นหา/รวมยอดตามรุ่นได้ตรงกัน
 */
class CarModels
{
    /**
     * รุ่นของรถจากใบสั่งรถเข้าสต็อก (car_order) — ใช้เมื่อ VIN ไม่มีใบขายที่ส่งมอบแล้ว
     * เช่นรถที่ขายผ่านช่องทางอื่น ระบบขายไม่มีข้อมูลลูกค้า/วันส่งมอบ แต่ยังรู้ว่าเป็นรุ่นอะไร
     *
     * @return object{model_id:int, model:string, submodel:?string}|null
     */
    public static function ofStockVin(?string $vin, ?string $brand): ?object
    {
        $vin = DeliveredCars::normaliseVin($vin);

        if ($vin === null) {
            return null;
        }

        return DB::connection('sales')
            ->table('car_order as o')
            ->join('tb_brand as b', 'b.id', '=', 'o.brand')
            ->join('tb_carmodels as m', 'm.id', '=', 'o.model_id')
            ->leftJoin('tb_subcarmodels as sm', 'sm.id', '=', 'o.subModel_id')
            ->where('b.name', (string) $brand)
            ->whereNull('o.deleted_at')
            ->whereRaw("UPPER(REPLACE(o.vin_number, ' ', '')) = ?", [$vin])
            ->orderByDesc('o.id')
            ->first([
                'm.id as model_id',
                'm.Name_TH as model',
                DB::raw("COALESCE(NULLIF(TRIM(sm.detail), ''), NULLIF(TRIM(sm.name), '')) as submodel"),
            ]);
    }

    /**
     * @return Collection<int, array{id:int, name:string, submodels:list<string>}>
     */
    public static function forBrand(?string $brand): Collection
    {
        $models = DB::connection('sales')
            ->table('tb_carmodels as m')
            ->join('tb_brand as b', 'b.id', '=', 'm.brand')
            ->where('b.name', (string) $brand)
            ->whereNull('m.deleted_at')
            ->where('m.Active', 'active')
            ->orderBy('m.Name_TH')
            ->get(['m.id', 'm.Name_TH as name']);

        if ($models->isEmpty()) {
            return collect();
        }

        // ชื่อรุ่นย่อยใช้กติกาเดียวกับ DeliveredCars — detail ถ้ามี ไม่งั้น name
        $submodels = DB::connection('sales')
            ->table('tb_subcarmodels')
            ->whereIn('model_id', $models->pluck('id'))
            ->whereNull('deleted_at')
            ->where('active', 'active')
            ->orderBy('id')
            ->get(['model_id', DB::raw("COALESCE(NULLIF(TRIM(detail), ''), NULLIF(TRIM(name), '')) as label")])
            ->filter(fn ($row) => $row->label !== null)
            ->groupBy('model_id');

        return $models->map(fn ($model) => [
            'id' => (int) $model->id,
            'name' => $model->name,
            'submodels' => ($submodels->get($model->id) ?? collect())->pluck('label')->unique()->values()->all(),
        ])->values();
    }
}
