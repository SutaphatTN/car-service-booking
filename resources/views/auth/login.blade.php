@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')
@section('subheading', 'สำหรับเจ้าหน้าที่ภายในเท่านั้น')
@section('body-class', 'page-login')

@section('content')
    <div class="login-shell">
        <div class="card login-card">
            <span class="login-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 15l2.6-5.2A3 3 0 0 1 13.3 8h5.4a3 3 0 0 1 2.7 1.8L24 15"/>
                    <path d="M5 15h22a2 2 0 0 1 2 2v4h-3.2M8.2 21H3v-4a2 2 0 0 1 2-2"/>
                    <circle cx="10.5" cy="21" r="2.8"/>
                    <circle cx="21.5" cy="21" r="2.8"/>
                    <path d="M13.3 21h5.4"/>
                </svg>
            </span>

            <h2>เข้าสู่ระบบ</h2>
            <p class="hint">กรอกชื่อผู้ใช้และรหัสผ่านที่ได้รับจากผู้ดูแลระบบ</p>

            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="login-fields">
                    <label class="field">ชื่อผู้ใช้
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input name="username" value="{{ old('username') }}" autocomplete="username"
                                   placeholder="ชื่อผู้ใช้ของคุณ"
                                   class="@error('username') is-invalid @enderror" required autofocus>
                        </span>
                        @error('username') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">รหัสผ่าน
                        <span class="input-icon has-toggle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="2.5"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            </svg>
                            <input type="password" name="password" id="loginPassword" autocomplete="current-password"
                                   placeholder="รหัสผ่าน"
                                   class="@error('password') is-invalid @enderror" required>
                            {{-- ปุ่มดูรหัสผ่าน — พิมพ์ผิดแล้วมองไม่เห็นเป็นสาเหตุที่เข้าระบบไม่ได้บ่อยที่สุด --}}
                            <button type="button" class="pw-toggle" id="pwToggle"
                                    aria-label="แสดงรหัสผ่าน" aria-pressed="false" title="แสดงรหัสผ่าน">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                    <path class="pw-slash" d="M4 20 20 4"/>
                                </svg>
                            </button>
                        </span>
                        @error('password') <span class="err">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="check">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    จดจำการเข้าสู่ระบบ
                </label>

                <div class="actions">
                    <button type="submit" class="primary" data-busy="กำลังเข้าสู่ระบบ…">เข้าสู่ระบบ</button>
                </div>
            </form>

            <p class="login-foot">ลืมรหัสผ่าน? ติดต่อผู้ดูแลระบบเพื่อขอรหัสใหม่</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // สลับการมองเห็นรหัสผ่าน
        (function () {
            var toggle = document.getElementById('pwToggle');
            var input = document.getElementById('loginPassword');
            if (!toggle || !input) return;

            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';

                var label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
                toggle.setAttribute('aria-pressed', String(show));
                toggle.setAttribute('aria-label', label);
                toggle.title = label;

                input.focus();
            });
        })();
    </script>
@endpush
