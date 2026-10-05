<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CallLog;
use App\Support\Schedule;
use App\Support\ServiceQueueImporter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * รายการ "ต้องโทรหาลูกค้า" — คิวเช็คระยะที่ใกล้ถึงกำหนดแต่ยังไม่ได้ยืนยันวันเวลา
 * ข้อมูลมาจากตาราง appointments ตารางเดียว ไม่ได้เก็บซ้ำอีกที่
 */
class CallListController extends Controller
{
    /** โทรล่วงหน้ากี่วันก่อนถึงวันครบกำหนด — ค่าเริ่มต้นตอนเปิดหน้า */
    public const CALL_AHEAD_DAYS = 7;

    /** ช่วงวันที่มีปุ่มให้กดสลับบนหน้า (ส่ง ?days= อื่นมาเองก็ยังได้) */
    public const CALL_AHEAD_PRESETS = [7, 30];

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
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'appointment_date.required' => 'กรุณาเลือกวันที่นัด',
            'appointment_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'appointment_time.required' => 'กรุณาเลือกเวลานัด',
            'appointment_time.date_format' => 'รูปแบบเวลาไม่ถูกต้อง',
            'note.max' => 'หมายเหตุยาวเกิน 1,000 ตัวอักษร',
        ]);

        $bookedAt = $data['appointment_date'].' '.$data['appointment_time'].':00';

        CallLog::record($appointment, 'confirmed', ['booked_at' => $bookedAt]);

        $appointment->update([
            'appointment_at' => $bookedAt,
            // ส่งช่องว่างมา = ลบหมายเหตุเดิม (ป็อปอัปเติมของเดิมไว้ให้แก้แล้ว)
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'confirmed_at' => now(),
            // ติดต่อได้แล้ว ล้างสถานะโทรไม่ติดทิ้ง ไม่งั้นค้างเป็นข้อมูลเก่าในหน้ารายการนัดหมาย
            'unreachable_at' => null,
            'recall_at' => null,
            'unreachable_count' => 0,
        ]);

        return back()->with('status', 'ยืนยันนัดของ '.$appointment->fullName().' แล้ว');
    }

    /**
     * เลื่อนวันโทร — โทรไม่ติด หรือโทรติดแล้วแต่ลูกค้ายังไม่สะดวกนัด
     *
     * ไม่ขยับ appointment_at เพราะนั่นคือวันครบกำหนดตามตารางเช็คระยะ (ระบบดึงข้อมูล
     * ใช้คำนวณต่อ) วันโทรซ้ำเก็บแยกไว้ที่ recall_at แล้วหน้า "ต้องโทรหา" จะยึดวันนั้นแทน
     *
     * ตัวนับ unreachable_count ขยับเฉพาะตอนโทรไม่ติดจริง — ลูกค้าที่รับสายทุกครั้ง
     * แต่ขอเลื่อนไปก่อน ไม่ควรถูกบันทึกว่า "ติดต่อไม่ได้ 4 ครั้ง"
     */
    public function unreachable(Request $request, Appointment $appointment): RedirectResponse
    {
        if (! $appointment->needsCall()) {
            return back()->withErrors([
                'recall_date' => 'นัดของ '.$appointment->fullName().' ยืนยัน ปิดงาน หรือปิดคิวไปแล้ว',
            ]);
        }

        // กดผิด/ติดต่อได้แล้วโดยไม่ได้นัด — เอาสถานะออกให้กลับไปเป็นคิวรอโทรตามปกติ
        if ($request->boolean('clear')) {
            // ผลที่กดผิดไม่นับในรายงานการโทร
            CallLog::cancelLatest($appointment, ['unreachable', 'not_ready']);

            $appointment->update([
                'unreachable_at' => null,
                'recall_reason' => null,
                'recall_at' => null,
                'unreachable_count' => 0,
            ]);

            return back()->with('status', 'ล้างสถานะเลื่อนโทรของ '.$appointment->fullName().' แล้ว');
        }

        $data = $request->validate([
            'recall_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'recall_reason' => ['required', Rule::in(array_keys(Appointment::RECALL_REASONS))],
        ], [
            'recall_date.required' => 'กรุณาเลือกวันที่จะโทรซ้ำ',
            'recall_date.date_format' => 'รูปแบบวันที่ไม่ถูกต้อง',
            'recall_date.after_or_equal' => 'วันที่จะโทรซ้ำต้องเป็นวันนี้หรือหลังจากนี้',
            'recall_reason.required' => 'กรุณาเลือกเหตุผลที่เลื่อน',
            'recall_reason.in' => 'เหตุผลที่เลือกไม่ถูกต้อง',
        ]);

        $unreachable = $data['recall_reason'] === 'unreachable';

        // เลื่อนไปวันเดิมซ้ำไม่นับเป็นการโทรไม่ติดรอบใหม่ — กันกดปุ่มรัว ๆ แล้วตัวนับพุ่งเป็นสิบ
        // (หน้าเว็บล็อกปุ่มให้อยู่แล้ว ตรงนี้กันอีกชั้นเผื่อยิงซ้ำจากที่อื่นหรือเน็ตสะดุดแล้วส่งซ้ำ)
        $sameDay = $appointment->recall_at?->toDateString() === $data['recall_date'];

        // ส่งซ้ำแบบเดิมเป๊ะไม่ใช่การโทรรอบใหม่ ไม่ต้องลงประวัติซ้ำ
        if (! ($sameDay && $appointment->recall_reason === $data['recall_reason'])) {
            CallLog::record($appointment, $data['recall_reason'], ['recall_at' => $data['recall_date']]);
        }

        $appointment->update([
            'unreachable_at' => now(),
            'recall_reason' => $data['recall_reason'],
            'recall_at' => $data['recall_date'],
            'unreachable_count' => $appointment->unreachable_count + ($unreachable && ! $sameDay ? 1 : 0),
        ]);

        return back()->with('status',
            ($unreachable
                ? 'บันทึกว่าติดต่อ '.$appointment->fullName().' ไม่ได้'
                : 'บันทึกว่า '.$appointment->fullName().' ยังไม่สะดวก')
            .' — จะโทรซ้ำ '.thai_date($data['recall_date'], short: true));
    }

    /**
     * ลูกค้าไม่เข้าเช็คระยะครั้งนี้ — ปิดคิวโดยไม่ได้เช็ค
     *
     * ลบแถวทิ้งแทนไม่ได้ คิวที่ดึงมาจากฝ่ายขายจะถูกสร้างขึ้นใหม่จาก salecar_id เดิม
     * ในรอบ import ถัดไป แล้วลูกค้าที่บอกว่าไม่เอาแล้วก็จะเด้งกลับเข้ารายการโทรอีก
     */
    public function decline(Request $request, Appointment $appointment, ServiceQueueImporter $importer): RedirectResponse
    {
        // กดผิด หรือลูกค้าเปลี่ยนใจ — เอากลับเข้ารายการต้องโทรหาตามเดิม
        if ($request->boolean('clear')) {
            if (! $appointment->isDeclined()) {
                return back()->withErrors(['declined_reason' => 'คิวของ '.$appointment->fullName().' ไม่ได้ถูกปิดไว้']);
            }

            CallLog::cancelLatest($appointment, ['declined']);

            $appointment->update([
                'declined_at' => null,
                'declined_reason' => null,
                'declined_note' => null,
            ]);

            return back()->with('status', 'เอาคิวของ '.$appointment->fullName().' กลับเข้ารายการต้องโทรหาแล้ว');
        }

        if (! $appointment->needsCall()) {
            return back()->withErrors([
                'declined_reason' => 'นัดของ '.$appointment->fullName().' ยืนยัน ปิดงาน หรือปิดคิวไปแล้ว',
            ]);
        }

        $data = $request->validate([
            'declined_reason' => ['required', Rule::in(array_keys(Appointment::DECLINE_REASONS))],
            'declined_note' => ['nullable', 'string', 'max:200'],
        ], [
            'declined_reason.required' => 'กรุณาเลือกเหตุผล',
            'declined_reason.in' => 'เหตุผลที่เลือกไม่ถูกต้อง',
            'declined_note.max' => 'รายละเอียดยาวเกิน 200 ตัวอักษร',
        ]);

        CallLog::record($appointment, 'declined', [
            'declined_reason' => $data['declined_reason'],
            'note' => $data['declined_note'] ?? null,
        ]);

        $appointment->update([
            'declined_at' => now(),
            'declined_reason' => $data['declined_reason'],
            'declined_note' => $data['declined_note'] ?? null,
            // ธงเลื่อนโทรหมดความหมายแล้ว ล้างทิ้งไม่ให้ค้างเป็นข้อมูลเก่า
            'unreachable_at' => null,
            'recall_reason' => null,
            'recall_at' => null,
        ]);

        $message = 'ปิดคิวของ '.$appointment->fullName().' แล้ว — '
            .Appointment::DECLINE_REASONS[$data['declined_reason']];

        // ครั้งนี้ไม่เข้าแต่ยังเป็นลูกค้าอยู่ — เดินตารางเช็คระยะต่อเหมือนตอนปิดงาน
        if ($data['declined_reason'] === 'skip') {
            $next = $importer->createNextFor($appointment);

            if ($next) {
                $message .= ' · คิวครั้งที่ '.$next->service_no.' ครบกำหนด '
                    .thai_date($next->appointment_at, short: true).' ระบบสร้างให้แล้ว';
            } elseif ($appointment->isFromSales()) {
                $message .= ' · ครบตารางเช็คระยะของรุ่นนี้แล้ว ไม่มีคิวถัดไป';
            }
        }

        return back()->with('status', $message);
    }
}
