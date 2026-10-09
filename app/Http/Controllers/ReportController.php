<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /** รายงานนัดหมายตามช่วงวันที่ */
    public function index(Request $request): View
    {
        [$from, $to, $appointments] = $this->report($request);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'appointments' => $appointments,
            'summary' => $this->summarise($appointments, $from, $to),
        ]);
    }

    /** ดาวน์โหลดเป็น Excel — ช่วงวันที่เดียวกับที่เห็นบนหน้าจอ */
    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to, $appointments] = $this->report($request);

        $rows = [[
            'วันที่นัด', 'เวลา', 'ชื่อ - นามสกุล', 'เบอร์โทร', 'เบอร์สำรอง', 'รุ่น', 'รุ่นย่อย', 'เลขตัวถัง VIN',
            'ครั้งที่', 'ส่งมอบจริง', 'เลขไมล์ (กม.)', 'สถานะ', 'หมายเหตุ', 'ผู้บันทึก',
        ]];

        foreach ($appointments as $appointment) {
            $rows[] = [
                thai_date($appointment->appointment_at, short: true),
                $appointment->appointment_at->format('H:i'),
                $appointment->fullName(),
                phone_format($appointment->phone),
                phone_format($appointment->phone2),
                $appointment->model_name,
                $appointment->submodel_name,
                $appointment->vin,
                $appointment->service_no,
                $appointment->delivered_at ? thai_date($appointment->delivered_at, short: true) : null,
                $appointment->mileage,
                $appointment->status(),
                $appointment->note,
                $appointment->creator?->name,
            ];
        }

        $range = $from->isSameDay($to)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d').'_'.$to->format('Y-m-d');

        return (new XlsxWriter)
            ->addSheet('นัดหมาย', $rows, [13, 8, 32, 14, 14, 20, 22, 21, 8, 13, 13, 16, 40, 18])
            ->download('รายงานนัดหมาย_'.$range.'.xlsx');
    }

    /** @return array{Carbon, Carbon, Collection<int, Appointment>} */
    private function report(Request $request): array
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

        return [$from, $to, $appointments];
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
