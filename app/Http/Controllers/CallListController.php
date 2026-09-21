<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\Schedule;
use Carbon\Carbon;
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
            // มุมมองรายเดือน เช่น ?month=2026-10 — ไม่ส่งมา = ใช้มุมมองปกติแบบนับวันข้างหน้า
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'month.date_format' => 'รูปแบบเดือนไม่ถูกต้อง',
            'month.after_or_equal' => 'เลือกเดือนได้ตั้งแต่ปี 2543 เป็นต้นไป',
            'month.before_or_equal' => 'เลือกเดือนได้ถึงปี 2643',
        ]);

        $days = (int) ($filters['days'] ?? self::CALL_AHEAD_DAYS);
        $search = $filters['q'] ?? null;
        $month = isset($filters['month'])
            ? Carbon::parse($filters['month'].'-01')->startOfMonth()
            : null;

        // ใช้ซ้ำสองรอบ (รายการที่แบ่งหน้า + ตัวนับคิวค้าง) จึงทำเป็นฟังก์ชันไว้เรียกใหม่
        $scope = fn () => $month
            ? Appointment::needingCallInMonth($month)
            : Appointment::needingCall($days);

        $calls = $scope()->with(['title', 'brand'])->search($search);

        return view('calls.index', [
            'days' => $days,
            'month' => $month,
            'search' => $search,
            // รายเดือนต้องดันคิวค้างขึ้นบนสุด ส่วนมุมมองปกติเรียงตามวันก็ได้ลำดับนั้นอยู่แล้ว
            'calls' => ($month ? $calls->overdueFirst() : $calls->callOrdered())
                ->paginate(20)
                ->withQueryString(),
            // นับจากคิวทั้งหมดที่เลยกำหนด ไม่ขึ้นกับคำค้น จะได้ไม่พลาดรายการที่ค้นไม่เจอ
            'overdueCount' => $scope()
                ->where('call_due_date', '<', now()->toDateString())
                ->count(),
            'timeSlots' => Schedule::timeSlots(),
            'monthCounts' => $month ? $this->monthCounts() : collect(),
        ]);
    }

    /**
     * จำนวนคิวที่ต้องโทรแยกตามเดือน — ใช้ทำช่องกระโดดข้ามเดือน
     *
     * ไล่กด ‹ › ทีละเดือนแล้วรอโหลดทุกครั้งช้าเกินไป ช่องเลือกจึงต้องเห็นทั้งช่วงงาน
     * พร้อมจำนวนคิวของแต่ละเดือนในทีเดียว จะได้เลือกไปเดือนที่ต้องการได้เลย
     *
     * @return \Illuminate\Support\Collection<string, int> คีย์เป็น Y-m เรียงจากเก่าไปใหม่
     */
    private function monthCounts(): \Illuminate\Support\Collection
    {
        return Appointment::unhandled()
            ->selectRaw("DATE_FORMAT(call_due_date, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym')
            ->map(fn ($total) => (int) $total);
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
            // ติดต่อได้แล้ว ล้างสถานะโทรไม่ติดทิ้ง ไม่งั้นค้างเป็นข้อมูลเก่าในหน้ารายการนัดหมาย
            'unreachable_at' => null,
            'recall_at' => null,
            'unreachable_count' => 0,
        ]);

        return back()->with('status', 'ยืนยันนัดของ '.$appointment->fullName().' แล้ว');
    }

    /**
     * โทรแล้วไม่ติด — บันทึกว่าจะโทรซ้ำวันไหน
     *
     * ไม่ขยับ appointment_at เพราะนั่นคือวันครบกำหนดตามตารางเช็คระยะ (ระบบดึงข้อมูล
     * ใช้คำนวณต่อ) วันโทรซ้ำเก็บแยกไว้ที่ recall_at แล้วหน้า "ต้องโทรหา" จะยึดวันนั้นแทน
     */
    public function unreachable(Request $request, Appointment $appointment): RedirectResponse
    {
        if (! $appointment->needsCall()) {
            return back()->withErrors([
                'recall_date' => 'นัดของ '.$appointment->fullName().' ยืนยันหรือปิดงานไปแล้ว',
            ]);
        }

        // กดผิด/ติดต่อได้แล้วโดยไม่ได้นัด — เอาสถานะออกให้กลับไปเป็นคิวรอโทรตามปกติ
        if ($request->boolean('clear')) {
            $appointment->update([
                'unreachable_at' => null,
                'recall_at' => null,
                'unreachable_count' => 0,
            ]);

            return back()->with('status', 'ล้างสถานะติดต่อไม่ได้ของ '.$appointment->fullName().' แล้ว');
        }

        $data = $request->validate([
            'recall_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'recall_date.required' => 'กรุณาเลือกวันที่จะโทรซ้ำ',
            'recall_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'recall_date.after_or_equal' => 'วันที่จะโทรซ้ำต้องเป็นวันนี้หรือหลังจากนี้',
        ]);

        // เลื่อนไปวันเดิมซ้ำไม่นับเป็นการโทรไม่ติดรอบใหม่ — กันกดปุ่มรัว ๆ แล้วตัวนับพุ่งเป็นสิบ
        // (หน้าเว็บล็อกปุ่มให้อยู่แล้ว ตรงนี้กันอีกชั้นเผื่อยิงซ้ำจากที่อื่นหรือเน็ตสะดุดแล้วส่งซ้ำ)
        $sameDay = $appointment->recall_at?->toDateString() === $data['recall_date'];

        $appointment->update([
            'unreachable_at' => now(),
            'recall_at' => $data['recall_date'],
            'unreachable_count' => $appointment->unreachable_count + ($sameDay ? 0 : 1),
        ]);

        return back()->with('status',
            'บันทึกว่าติดต่อ '.$appointment->fullName().' ไม่ได้ — จะโทรซ้ำ '
            .thai_date($data['recall_date'], short: true));
    }
}
