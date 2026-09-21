@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')
@section('subheading', 'สำหรับเจ้าหน้าที่ภายในเท่านั้น')

@section('content')
    <div class="card login-card">
        <h2>เข้าสู่ระบบ</h2>
        <p class="hint">กรอกชื่อผู้ใช้และรหัสผ่านที่ได้รับจากผู้ดูแลระบบ</p>

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label class="field">ชื่อผู้ใช้
                <input name="username" value="{{ old('username') }}" autocomplete="username"
                       class="@error('username') is-invalid @enderror" required autofocus>
                @error('username') <span class="err">{{ $message }}</span> @enderror
            </label>

            <label class="field" style="margin-top:12px">รหัสผ่าน
                <input type="password" name="password" autocomplete="current-password"
                       class="@error('password') is-invalid @enderror" required>
                @error('password') <span class="err">{{ $message }}</span> @enderror
            </label>

            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                จดจำการเข้าสู่ระบบ
            </label>

            <div class="actions" style="margin-top:16px">
                <button type="submit" class="primary" data-busy="กำลังเข้าสู่ระบบ…">เข้าสู่ระบบ</button>
            </div>
        </form>
    </div>
@endsection
