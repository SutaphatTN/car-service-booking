@extends('layouts.app')

@section('title', 'รายชื่อผู้ใช้งาน')

@section('content')
    <div class="page-head">
        <div>
            <h2>รายชื่อผู้ใช้งาน</h2>
            <p class="muted">ทั้งหมด {{ baht($users->count()) }} บัญชี · เห็นได้เฉพาะสิทธิ์ Admin</p>
        </div>
        <a href="{{ route('users.create') }}"><button type="button" class="primary">+ ลงทะเบียนผู้ใช้</button></a>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:200px">ผู้ใช้</th>
                    <th style="width:150px">สิทธิ์</th>
                    <th>อีเมล</th>
                    <th class="right" style="width:120px">บันทึกนัดแล้ว</th>
                    <th style="width:110px">สถานะ</th>
                    <th style="width:120px">สร้างเมื่อ</th>
                    <th class="right" style="width:90px">จัดการ</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <b>{{ $user->name }}</b>
                            <div class="sub">{{ '@'.$user->username }}</div>
                        </td>
                        <td><span class="badge role-{{ $user->role->value }}">{{ $user->role->label() }}</span></td>
                        <td class="sub">{{ $user->email ?: '-' }}</td>
                        <td class="right">{{ baht($user->appointments_count) }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge ok">ใช้งานอยู่</span>
                            @else
                                <span class="badge off">ปิดใช้งาน</span>
                            @endif
                        </td>
                        <td class="sub">{{ $user->created_at ? thai_date($user->created_at, short: true) : '-' }}</td>
                        <td class="right">
                            <a href="{{ route('users.edit', $user) }}"><button type="button" class="ghost">แก้ไข</button></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="muted" style="font-size:13px">
        รหัสผ่านไม่แสดงบนหน้าจอ ดูรหัสจริงได้ที่คอลัมน์ <code>password_plain</code> ในตาราง <code>users</code>
        ส่วนการปิดใช้งานบัญชีทำได้โดยตั้ง <code>is_active = 0</code>
    </p>
@endsection
