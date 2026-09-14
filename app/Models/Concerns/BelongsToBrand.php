<?php

namespace App\Models\Concerns;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ผูกตารางกับยี่ห้อรถ
 *
 *  - อ่าน  : global scope ชื่อ 'brand' กรองเฉพาะยี่ห้อที่กำลังทำงานอยู่ให้อัตโนมัติ
 *            ถ้าต้องการข้ามยี่ห้อจริง ๆ ให้เรียก ->withoutGlobalScope('brand')
 *  - เขียน : ตอน create จะเติม brand_id ให้เองถ้ายังไม่ได้ระบุมา
 */
trait BelongsToBrand
{
    protected static function bootBelongsToBrand(): void
    {
        static::addGlobalScope('brand', function (Builder $query): void {
            $brandId = current_brand_id();

            // ยังไม่ได้เข้าสู่ระบบ (เช่นสั่งจาก tinker หรือ console) = ไม่กรอง
            if ($brandId === null) {
                return;
            }

            $query->where($query->getModel()->getTable().'.brand_id', $brandId);
        });

        static::creating(function (Model $model): void {
            $model->brand_id ??= current_brand_id();
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
