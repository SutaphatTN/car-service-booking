<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * ผู้ใช้งานภายใน — รหัสผ่านถูกเก็บสองแบบ
 *   password       : bcrypt hash ใช้ตอนตรวจสอบการเข้าสู่ระบบ
 *   password_plain : รหัสตัวจริง เอาไว้เปิดดูจากฐานข้อมูลเท่านั้น (ไม่แสดงบนหน้าจอ)
 *
 * ยี่ห้อรถ: brand_id คือยี่ห้อประจำตัว ส่วนยี่ห้ออื่นที่สลับไปดูได้เก็บไว้ในตาราง brand_user
 */
#[Fillable(['username', 'name', 'email', 'role', 'password', 'password_plain', 'is_active', 'brand_id'])]
#[Hidden(['password', 'password_plain', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSa(): bool
    {
        return $this->role === Role::Sa;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }

    /** จัดการผู้ใช้ (ลงทะเบียน / ดูรายชื่อ) ทำได้เฉพาะ admin */
    public function canManageUsers(): bool
    {
        return $this->role === Role::Admin;
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    /** ยี่ห้อประจำตัว — ยี่ห้อที่เห็นตอนเข้าสู่ระบบครั้งแรก */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** ยี่ห้ออื่นที่อนุญาตให้สลับไปดูได้ (เพิ่มแถวเองในตาราง brand_user) */
    public function extraBrands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    /**
     * ยี่ห้อทั้งหมดที่บัญชีนี้เข้าถึงได้ = ยี่ห้อประจำตัว + ยี่ห้อใน brand_user
     * นับเฉพาะยี่ห้อที่ยังเปิดใช้งาน เรียงตาม sort_order เหมือนที่อื่น
     *
     * @return Collection<int, Brand>
     */
    public function accessibleBrands()
    {
        return Brand::active()
            ->where(fn ($query) => $query
                ->where('id', $this->brand_id)
                ->orWhereIn('id', $this->extraBrands()->select('brands.id')))
            ->get();
    }

    /** @return list<int> */
    public function accessibleBrandIds(): array
    {
        return $this->accessibleBrands()->pluck('id')->all();
    }

    public function canAccessBrand(int $brandId): bool
    {
        return in_array($brandId, $this->accessibleBrandIds(), true);
    }

    /** มีให้สลับมากกว่าหนึ่งยี่ห้อถึงจะโชว์ปุ่มสลับ */
    public function canSwitchBrand(): bool
    {
        return count($this->accessibleBrandIds()) > 1;
    }
}
