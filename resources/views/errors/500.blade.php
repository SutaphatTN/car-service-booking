@extends('errors.layout')

@section('code', '500')
@section('title', 'ระบบขัดข้อง')
@section('message', 'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง หากยังไม่หายให้แจ้งผู้ดูแลระบบ')

@section('detail')
    ถ้าเพิ่งติดตั้งระบบใหม่ ให้ตรวจว่ารันไฟล์ <code>database/sql/schema.sql</code> กับฐานข้อมูลแล้วหรือยัง
    และดูรายละเอียดเพิ่มเติมได้ที่ <code>storage/logs/laravel.log</code>
@endsection
