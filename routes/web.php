<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BrandSwitchController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CallListController;
use App\Http\Controllers\CallReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TechnicianWorkController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| เข้าสู่ระบบ
|--------------------------------------------------------------------------
*/

Route::get('login', [LoginController::class, 'create'])->name('login');
// จำกัดการลองรหัสผ่าน 10 ครั้ง/นาที ต่อ 1 IP กันการสุ่มรหัส
Route::post('login', [LoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| ระบบภายใน (ต้องเข้าสู่ระบบก่อน)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    /*
    | สลับยี่ห้อรถที่กำลังดูอยู่ — ยี่ห้อที่เลือกได้มาจากยี่ห้อประจำตัว + ตาราง brand_user
    */
    Route::post('brand/switch', [BrandSwitchController::class, 'store'])->name('brand.switch');
    Route::post('brand/reset', [BrandSwitchController::class, 'destroy'])->name('brand.reset');

    /*
    | หน้าที่ทำงานกับข้อมูลนัดหมาย — ข้อมูลถูกกรองตามยี่ห้อที่ใช้งานอยู่โดยอัตโนมัติ
    */
    Route::middleware('brand')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/calls', [CallReportController::class, 'index'])->name('reports.calls');
        Route::get('reports/calls/export', [CallReportController::class, 'export'])->name('reports.calls.export');

        /*
        | รายการต้องโทรหาลูกค้า — คิวเช็คระยะที่ใกล้ถึงกำหนดแต่ยังไม่ได้ยืนยัน
        */
        Route::get('calls', [CallListController::class, 'index'])->name('calls.index');
        Route::patch('calls/{appointment}/confirm', [CallListController::class, 'confirm'])->name('calls.confirm');
        Route::patch('calls/{appointment}/unreachable', [CallListController::class, 'unreachable'])
            ->name('calls.unreachable');
        // ลูกค้าไม่เข้าเช็คระยะครั้งนี้ — ปิดคิวโดยไม่ได้เช็ค (ส่ง clear=1 เพื่อเอากลับ)
        Route::patch('calls/{appointment}/decline', [CallListController::class, 'decline'])
            ->name('calls.decline');
        Route::patch('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
            ->name('appointments.complete');

        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        // ค้น VIN ในระบบขายระหว่างกรอกฟอร์ม — เติมรุ่นให้เอง และบอกว่ามีคิวค้างอยู่ไหม
        Route::get('appointments/vin-lookup', [AppointmentController::class, 'lookupVin'])
            ->name('appointments.vin-lookup');
        // แก้ไข/ยกเลิกการยืนยัน — เฉพาะผู้ใช้ใน APPOINTMENT_EDITOR_IDS (ตรวจใน request/controller)
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::patch('appointments/{appointment}/unconfirm', [AppointmentController::class, 'unconfirm'])
            ->name('appointments.unconfirm');

        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])
            ->middleware('role:sa')
            ->name('appointments.destroy');

        /*
        | ปริมาณงานช่าง — ปฏิทินรายวัน + ลงงาน (สิทธิ์แก้/ลบตรวจใน controller)
        */
        Route::get('work', [TechnicianWorkController::class, 'index'])->name('work.index');
        Route::get('work/person', [TechnicianWorkController::class, 'person'])->name('work.person');
        Route::get('work/export', [TechnicianWorkController::class, 'export'])->name('work.export');
        Route::get('work/person/export', [TechnicianWorkController::class, 'personExport'])->name('work.person.export');
        Route::post('work', [TechnicianWorkController::class, 'store'])->name('work.store');
        Route::patch('work/{job}', [TechnicianWorkController::class, 'update'])->name('work.update');
        Route::delete('work/{job}', [TechnicianWorkController::class, 'destroy'])->name('work.destroy');

        // รายชื่อช่าง — admin เท่านั้น
        Route::middleware('role:admin')->group(function () {
            Route::get('technicians', [TechnicianController::class, 'index'])->name('technicians.index');
            Route::post('technicians', [TechnicianController::class, 'store'])->name('technicians.store');
            Route::patch('technicians/{technician}', [TechnicianController::class, 'update'])->name('technicians.update');
            Route::delete('technicians/{technician}', [TechnicianController::class, 'destroy'])->name('technicians.destroy');
        });
    });

    /*
    | จัดการผู้ใช้ — admin เท่านั้น
    */
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
