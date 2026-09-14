@extends('errors.layout')

@section('code', '403')
@section('title', 'ไม่มีสิทธิ์เข้าใช้งาน')
@section('message', 'บัญชีของคุณไม่มีสิทธิ์ใช้งานส่วนนี้ หากต้องการสิทธิ์เพิ่ม กรุณาติดต่อผู้ดูแลระบบ (SA)')

{{-- ข้อความเจาะจงจาก abort(403, '...') เช่นบัญชีที่ยังไม่ได้ผูกกับยี่ห้อรถ --}}
@if ($exception?->getMessage())
    @section('detail', $exception->getMessage())
@endif
