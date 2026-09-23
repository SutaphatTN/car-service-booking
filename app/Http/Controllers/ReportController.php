<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    /** รายงานนัดหมายตามช่วงวันที่ */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'from.date_format' => 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง',
            'to.date_format' => 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง',
        ]);

        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->endOfMonth()->toDateString())->startOfDay();

        // เลือกกลับด้านมาก็ยังใช้งานได้
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $appointments = Appointment::with(['title', 'creator'])
            // ลูกค้าไม่เข้าแล้ว = ไม่มีงานวันนั้นจริง นับรวมจะทำให้ยอดในรายงานบวม
            ->whereNull('declined_at')
            ->whereBetween('appointment_at', [$from, $to->copy()->endOfDay()])
            ->ordered()
            ->get();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'appointments' => $appointments,
            'summary' => $this->summarise($appointments, $from, $to),
        ]);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, mixed>
     */
    private function summarise($appointments, Carbon $from, Carbon $to): array
    {
        $byDay = $appointments->groupBy(fn (Appointment $appointment) => $appointment->appointment_at->toDateString());
        $busiest = $byDay->sortByDesc(fn ($items) => $items->count())->take(1);

        return [
            'total' => $appointments->count(),
            'span_days' => (int) $from->diffInDays($to) + 1,
            'active_days' => $byDay->count(),
            'average_per_active_day' => $byDay->isEmpty()
                ? 0
                : round($appointments->count() / $byDay->count(), 1),
            'busiest_date' => $busiest->keys()->first(),
            'busiest_count' => $busiest->first()?->count() ?? 0,
        ];
    }
}
