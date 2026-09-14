<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** จัดการผู้ใช้ — เข้าได้เฉพาะ admin (กำหนดไว้ที่ route) */
class UserController extends Controller
{
    /** เห็นผู้ใช้ทุกยี่ห้อ ไม่กรองตามยี่ห้อที่กำลังดูอยู่ จะได้ดูแลบัญชีได้จากที่เดียว */
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with(['brand', 'extraBrands'])
                ->withCount('appointments')
                ->orderBy('role')
                ->orderBy('username')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::options(),
            'brands' => Brand::active()->get(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => Role::options(),
            'brands' => Brand::active()->get(),
            'extraBrandIds' => $user->extraBrands()->pluck('brands.id')->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill([
            'username' => $request->validated('username'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'brand_id' => $request->validated('brand_id'),
            'is_active' => $request->boolean('is_active'),
        ]);

        // เว้นช่องรหัสผ่านไว้ = ไม่เปลี่ยน ถ้ากรอกมาก็อัปเดตทั้งสองคอลัมน์ให้ตรงกัน
        if ($password = $request->validated('password')) {
            $user->password = $password;
            $user->password_plain = $password;
        }

        $user->save();
        $user->extraBrands()->sync($request->extraBrandIds());

        return redirect()
            ->route('users.index')
            ->with('status', 'แก้ไขข้อมูลของ '.$user->username.' เรียบร้อยแล้ว');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'username' => $request->validated('username'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            // คอลัมน์ password ถูก cast เป็น hashed ส่วน password_plain เก็บรหัสจริงไว้ดูในฐานข้อมูล
            'password' => $request->validated('password'),
            'password_plain' => $request->validated('password'),
            'brand_id' => $request->validated('brand_id'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->extraBrands()->sync($request->extraBrandIds());

        return redirect()
            ->route('users.index')
            ->with('status', 'ลงทะเบียนผู้ใช้ '.$user->username.' เรียบร้อยแล้ว');
    }
}
