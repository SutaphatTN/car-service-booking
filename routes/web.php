<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ReportController;
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
    Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');

    Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])
        ->middleware('role:sa')
        ->name('appointments.destroy');

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
