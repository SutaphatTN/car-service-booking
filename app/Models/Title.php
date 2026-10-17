<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** คำนำหน้าชื่อ — เก็บแยกตาราง เพิ่ม/แก้ได้จากฐานข้อมูล */
class Title extends Model
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** จับคู่คำนำหน้าด้วยชื่อ ตัดช่องว่างและจุดออกกันคลาดเคลื่อน เช่น "Mr." กับ "Mr" */
    public static function matchKey(?string $name): string
    {
        return mb_strtolower(str_replace([' ', '.', "\u{00A0}"], '', (string) $name));
    }
}
