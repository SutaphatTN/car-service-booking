@extends('layouts.app')

@section('title', 'แก้ไขผู้ใช้')

@section('content')
    <div class="page-head">
        <div>
            <h2>แก้ไขผู้ใช้ {{ '@'.$user->username }}</h2>
            <p class="muted">สร้างเมื่อ {{ $user->created_at ? thai_date($user->created_at) : '-' }}</p>
        </div>
        <a href="{{ route('users.index') }}"><button type="button" class="ghost">← กลับรายชื่อผู้ใช้</button></a>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="card form-card">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PATCH')

            <div class="grid cols-2">
                <label class="field">ชื่อผู้ใช้ (สำหรับ login)
                    <input name="username" value="{{ old('username', $user->username) }}" autocomplete="off"
                           class="@error('username') is-invalid @enderror" required>
                    @error('username')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">ภาษาอังกฤษ ตัวเลข - และ _ เท่านั้น</span>
                    @enderror
                </label>

                <label class="field">ชื่อที่แสดงในระบบ
                    <input name="name" value="{{ old('name', $user->name) }}"
                           class="@error('name') is-invalid @enderror" required>
                    @error('name') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:14px">
                <label class="field">อีเมล (ไม่บังคับ)
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="off"
                           class="@error('email') is-invalid @enderror">
                    @error('email') <span class="err">{{ $message }}</span> @enderror
                </label>

                <label class="field">สิทธิ์การใช้งาน
                    <select name="role" class="@error('role') is-invalid @enderror" required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            @include('partials.user-brand-fields', [
                'currentBrandId' => $user->brand_id,
                'currentExtraBrandIds' => $extraBrandIds,
            ])

            <label class="field" style="margin-top:14px">รหัสผ่านปัจจุบัน
                <div class="input-reveal">
                    <input type="password" id="currentPassword" readonly tabindex="-1" autocomplete="off"
                           value="{{ $user->password_plain ?? '' }}"
                           placeholder="{{ $user->password_plain === null ? 'ไม่ได้เก็บรหัสอ่านได้ไว้' : '' }}">
                    <button type="button" class="ghost" id="revealBtn"
                            @disabled($user->password_plain === null)>แสดง</button>
                </div>
                <span class="hint-inline">
                    อ่านจากคอลัมน์ <code>password_plain</code> ในฐานข้อมูล · ช่องนี้แก้ไม่ได้
                    ถ้าจะเปลี่ยนรหัสให้กรอกในช่องด้านล่าง
                </span>
            </label>

            <div class="grid cols-2" style="margin-top:14px">
                <label class="field">รหัสผ่านใหม่
                    <input type="password" name="password" autocomplete="new-password"
                           class="@error('password') is-invalid @enderror">
                    @error('password')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</span>
                    @enderror
                </label>

                <label class="field">ยืนยันรหัสผ่านใหม่
                    <input type="password" name="password_confirmation" autocomplete="new-password">
                </label>
            </div>

            <label class="check">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                เปิดใช้งานบัญชีนี้
            </label>
            @error('is_active') <span class="err">{{ $message }}</span> @enderror

            @if ($user->is(auth()->user()))
                <div class="alert info" style="margin-top:16px">
                    นี่คือบัญชีที่คุณกำลังใช้งานอยู่ — เปลี่ยนสิทธิ์หรือปิดใช้งานตัวเองไม่ได้
                    หากเปลี่ยนรหัสผ่าน ครั้งหน้าต้อง login ด้วยรหัสใหม่
                </div>
            @endif

            <div class="actions" style="margin-top:16px">
                <button type="submit" class="primary">บันทึกการแก้ไข</button>
                <a href="{{ route('users.index') }}"><button type="button" class="ghost">ยกเลิก</button></a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        // ปุ่มแสดง/ซ่อนรหัสผ่านปัจจุบัน
        const currentPassword = document.getElementById('currentPassword');
        const revealBtn = document.getElementById('revealBtn');

        revealBtn.addEventListener('click', () => {
            const shown = currentPassword.type === 'text';

            currentPassword.type = shown ? 'password' : 'text';
            revealBtn.textContent = shown ? 'แสดง' : 'ซ่อน';
        });
    </script>
@endpush
