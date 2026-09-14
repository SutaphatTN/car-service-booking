<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * รายการ "ต้องโทรหาลูกค้า" — คิวเช็คระยะที่ใกล้ถึงกำหนดแต่ยังไม่ได้ยืนยันวันเวลา
 * ข้อมูลมาจากตาราง appointments ตารางเดียว ไม่ได้เก็บซ้ำอีกที่
 */
class CallListController extends Controller
{
    /** โทรล่วงหน้ากี่วันก่อนถึงวันครบกำหนด */
    public const CALL_AHEAD_DAYS = 30;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $days = (int) ($filters['days'] ?? self::CALL_AHEAD_DAYS);
        $search = $filters['q'] ?? null;

        return view('calls.index', [
            'days' => $days,
            'search' => $search,
            'calls' => Appointment::with(['title', 'brand'])
                ->needingCall($days)
                ->search($search)
                ->ordered()
                ->paginate(20)
                ->withQueryString(),
            // นับจากคิวทั้งหมดที่เลยกำหนด ไม่ขึ้นกับคำค้น จะได้ไม่พลาดรายการที่ค้นไม่เจอ
            'overdueCount' => Appointment::needingCall($days)
                ->where('appointment_at', '<', now()->startOfDay())
                ->count(),
            'timeSlots' => Schedule::timeSlots(),
        ]);
    }

    /** โทรแล้ว — บันทึกวันเวลาที่ตกลงกับลูกค้า */
    public function confirm(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
        ], [
            'appointment_date.required' => 'กรุณาเลือกวันที่นัด',
            'appointment_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'appointment_time.required' => 'กรุณาเลือกเวลานัด',
            'appointment_time.date_format' => 'รูปแบบเวลาไม่ถูกต้อง',
        ]);

        $appointment->update([
            'appointment_at' => $data['appointment_date'].' '.$data['appointment_time'].':00',
            'confirmed_at' => now(),
        ]);

        return back()->with('status', 'ยืนยันนัดของ '.$appointment->fullName().' แล้ว');
    }
}
