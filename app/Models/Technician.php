<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ช่าง — แยกตามยี่ห้อ
 *
 * ลบแล้วเป็น soft delete เพราะงานย้อนหลังของช่างคนนั้นยังต้องเห็นชื่ออยู่ในปฏิทินและรายงาน
 */
class Technician extends Model
{
    use BelongsToBrand;
    use SoftDeletes;

    protected $fillable = ['brand_id', 'name'];

    public function jobs(): HasMany
    {
        return $this->hasMany(TechnicianJob::class);
    }

    /** เรียงตามชื่อ */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name')->orderBy('id');
    }
}
