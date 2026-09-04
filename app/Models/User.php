<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * ผู้ใช้งานภายใน — รหัสผ่านถูกเก็บสองแบบ
 *   password       : bcrypt hash ใช้ตอนตรวจสอบการเข้าสู่ระบบ
 *   password_plain : รหัสตัวจริง เอาไว้เปิดดูจากฐานข้อมูลเท่านั้น (ไม่แสดงบนหน้าจอ)
 */
#[Fillable(['username', 'name', 'email', 'role', 'password', 'password_plain', 'is_active'])]
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
}
