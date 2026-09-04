@extends('layouts.app')

@section('title', 'ลงทะเบียนผู้ใช้')

@section('content')
    <div class="page-head">
        <div>
            <h2>ลงทะเบียนผู้ใช้ใหม่</h2>
            <p class="muted">สร้างบัญชีสำหรับเจ้าหน้าที่ภายใน</p>
        </div>
        <a href="{{ route('users.index') }}"><button type="button" class="ghost">← กลับรายชื่อผู้ใช้</button></a>
    </div>

    @if ($errors->any())
        <div class="alert error">กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง</div>
    @endif

    <div class="card form-card">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="grid cols-2">
                <label class="field">ชื่อผู้ใช้ (สำหรับ login)
                    <input name="username" value="{{ old('username') }}" autocomplete="off"
                           class="@error('username') is-invalid @enderror" required autofocus>
                    @error('username')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">ภาษาอังกฤษ ตัวเลข - และ _ เท่านั้น</span>
                    @enderror
                </label>

                <label class="field">ชื่อที่แสดงในระบบ
                    <input name="name" value="{{ old('name') }}"
                           class="@error('name') is-invalid @enderror" required>
                    @error('name') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:14px">
                <label class="field">อีเมล (ไม่บังคับ)
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="off"
                           class="@error('email') is-invalid @enderror">
                    @error('email') <span class="err">{{ $message }}</span> @enderror
                </label>

                <label class="field">สิทธิ์การใช้งาน
                    <select name="role" class="@error('role') is-invalid @enderror" required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', 'admin') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:14px">
                <label class="field">รหัสผ่าน
                    <input type="password" name="password" autocomplete="new-password"
                           class="@error('password') is-invalid @enderror" required>
                    @error('password')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">อย่างน้อย 6 ตัวอักษร</span>
                    @enderror
                </label>

                <label class="field">ยืนยันรหัสผ่าน
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
            </div>

            <label class="check">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                เปิดใช้งานบัญชีนี้ทันที
            </label>

            <div class="alert info" style="margin-top:16px">
                รหัสผ่านจะถูกเก็บสองแบบ — แบบ token (bcrypt) ใช้ตอน login และแบบอ่านได้ในคอลัมน์
                <code>password_plain</code> สำหรับเปิดดูจากฐานข้อมูลเท่านั้น
            </div>

            <div class="actions" style="margin-top:16px">
                <button type="submit" class="primary">ลงทะเบียน</button>
                <a href="{{ route('users.index') }}"><button type="button" class="ghost">ยกเลิก</button></a>
            </div>
        </form>
    </div>
@endsection
