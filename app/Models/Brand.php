<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ยี่ห้อรถที่ศูนย์บริการดูแล เช่น GWM / Wuling
 * เพิ่มยี่ห้อใหม่ด้วยการ INSERT ลงตาราง brands เอง ไม่มีหน้าจอจัดการในระบบ
 */
class Brand extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** ผู้ใช้ที่มียี่ห้อนี้เป็นยี่ห้อประจำตัว */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** ผู้ใช้ที่ได้สิทธิ์สลับเข้ามายี่ห้อนี้เพิ่มเติม */
    public function switchableUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
