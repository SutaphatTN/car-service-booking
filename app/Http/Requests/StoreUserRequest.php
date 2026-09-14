<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
            // ยี่ห้อประจำตัว + ยี่ห้ออื่นที่อนุญาตให้สลับไปดูได้
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'extra_brands' => ['array'],
            'extra_brands.*' => ['integer', 'exists:brands,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'กรุณากรอกชื่อผู้ใช้',
            'username.alpha_dash' => 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z 0-9 ขีดกลาง และขีดล่าง',
            'username.unique' => 'ชื่อผู้ใช้นี้มีคนใช้แล้ว',
            'name.required' => 'กรุณากรอกชื่อที่จะแสดงในระบบ',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้ไปแล้ว',
            'role.required' => 'กรุณาเลือกสิทธิ์การใช้งาน',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร',
            'password.confirmed' => 'ยืนยันรหัสผ่านไม่ตรงกัน',
            'brand_id.required' => 'กรุณาเลือกยี่ห้อประจำตัว',
            'brand_id.exists' => 'ไม่พบยี่ห้อที่เลือก',
            'extra_brands.*.exists' => 'ไม่พบยี่ห้อที่เลือก',
        ];
    }

    /**
     * ยี่ห้อที่ให้สลับไปดูได้ ไม่ต้องเก็บยี่ห้อประจำตัวซ้ำลงตาราง brand_user
     *
     * @return list<int>
     */
    public function extraBrandIds(): array
    {
        return collect($this->validated('extra_brands') ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $this->validated('brand_id'))
            ->unique()
            ->values()
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'email' => trim((string) $this->input('email')) ?: null,
        ]);
    }
}
