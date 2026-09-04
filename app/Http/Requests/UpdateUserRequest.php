<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $id = $this->route('user')->id;

        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::enum(Role::class)],
            // เว้นว่างไว้ = ใช้รหัสผ่านเดิม
            'password' => ['nullable', 'string', 'min:6', 'max:100', 'confirmed'],
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
            'password.min' => 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร',
            'password.confirmed' => 'ยืนยันรหัสผ่านไม่ตรงกัน',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'email' => trim((string) $this->input('email')) ?: null,
        ]);
    }

    /** กันแก้บัญชีตัวเองจนหลุดสิทธิ์ admin แล้วเข้าหน้านี้ไม่ได้อีก */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('user')->isNot($this->user())) {
                return;
            }

            if ($this->input('role') !== 'admin') {
                $validator->errors()->add('role', 'เปลี่ยนสิทธิ์ของบัญชีตัวเองไม่ได้ ให้ admin คนอื่นเปลี่ยนให้แทน');
            }

            if (! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'ปิดใช้งานบัญชีตัวเองไม่ได้');
            }
        });
    }
}
