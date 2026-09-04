<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('appointments.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'กรุณากรอกชื่อผู้ใช้',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
        ]);

        $credentials = [
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => 1,
        ];

        try {
            $ok = Auth::attempt($credentials, $request->boolean('remember'));
        } catch (QueryException $e) {
            // ฐานข้อมูลยังไม่พร้อม (ยังไม่ได้รัน schema.sql หรือ MySQL ไม่ทำงาน)
            report($e);

            throw ValidationException::withMessages([
                'username' => 'ยังเชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบว่ารันไฟล์ database/sql/schema.sql แล้ว และ MySQL ทำงานอยู่',
            ]);
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีนี้ถูกปิดใช้งาน',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('appointments.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
