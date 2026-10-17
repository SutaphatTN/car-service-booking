<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Title;
use App\Support\CarModels;
use App\Support\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /** ปฏิทินรายเดือน กดที่วันเพื่อดูว่ามีนัดอะไรบ้าง */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $month = isset($filters['month'])
            ? Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth()
            : now()->startOfMonth();

        // ปฏิทินเริ่มวันอาทิตย์ และเติมวันของเดือนข้างเคียงให้ครบสัปดาห์
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $byDate = Appointment::with(['title', 'creator'])
            // คิวที่ลูกค้าบอกว่าไม่เข้าแล้ว ไม่ใช่นัดอีกต่อไป ต้องไม่ไปกินที่บนปฏิทิน
            ->whereNull('declined_at')
            ->whereBetween('appointment_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->ordered()
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->appointment_at->toDateString());

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('calendar.index', [
            'month' => $month,
            'weeks' => $weeks,
            'byDate' => $byDate,
            'events' => $this->eventsPayload($byDate),
            'selectedDate' => $filters['date'] ?? $this->defaultSelectedDate($month),
            'titles' => Title::active()->get(),
            'timeSlots' => Schedule::timeSlots(),
            'carModels' => CarModels::forBrand(current_brand()?->name),
        ]);
    }

    /** วันที่เลือกไว้ตอนเปิดหน้า — วันนี้ถ้าอยู่ในเดือนที่ดู ไม่งั้นเป็นวันที่ 1 */
    private function defaultSelectedDate(Carbon $month): string
    {
        return now()->isSameMonth($month)
            ? now()->toDateString()
            : $month->toDateString();
    }

    /**
     * ข้อมูลนัดหมายทั้งเดือนในรูปแบบที่ฝั่งหน้าเว็บใช้แสดงแผงด้านขวาได้ทันที
     * (กดเปลี่ยนวันแล้วไม่ต้องโหลดหน้าใหม่)
     *
     * @param  Collection<string, Collection<int, Appointment>>  $byDate
     * @return array<string, list<array<string, string>>>
     */
    private function eventsPayload($byDate): array
    {
        return $byDate->map(fn ($appointments) => $appointments->map(fn (Appointment $appointment) => [
            'time' => $appointment->appointment_at->format('H:i'),
            'name' => $appointment->fullName(),
            'phone' => phone_format($appointment->phone) ?: 'ไม่มีเบอร์',
            'phone2' => $appointment->phone2 ? phone_format($appointment->phone2) : '',
            'mileage' => baht($appointment->mileage),
            'vin' => $appointment->vin ?? '',
            'model' => trim(($appointment->model_name ?? '').' '.($appointment->submodel_name ?? '')),
            'delivered' => $appointment->delivered_at ? thai_date($appointment->delivered_at, short: true) : '',
            'creator' => $appointment->creator?->name ?? '-',
            'note' => $appointment->note ?? '',
        ])->all())->all();
    }
}
