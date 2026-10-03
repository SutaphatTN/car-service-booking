<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CallLog;
use App\Support\XlsxWriter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * รายงานการโทร — ในช่วงวันที่เลือก มีคิวไหนต้องโทรบ้าง และโทรแล้วผลเป็นอย่างไร
 *
 * "ต้องโทร" ใช้เกณฑ์เดียวกับหน้า "ต้องโทรหา" (ล่วงหน้า CALL_AHEAD_DAYS วัน รวมคิวที่เลยกำหนด)
 * ผลการโทรอ่านจาก call_logs เพราะ appointments เก็บแค่สถานะล่าสุด
 */
class CallReportController extends Controller
{
    /** ตัวกรองบนแถบ — ผลการโทรทั้งสี่แบบ + ยังไม่ได้โทร */
    public const FILTERS = ['all', 'called', 'confirmed', 'unreachable', 'not_ready', 'declined', 'pending'];

    /** ชื่อตัวกรองบนหน้าจอ / ชื่อผลในไฟล์ Excel */
    public const RESULT_LABELS = [
        'all' => 'ทั้งหมด',
        'called' => 'โทรแล้ว',
        'pending' => 'ยังไม่ได้โทร',
    ] + CallLog::RESULTS;

    public function index(Request $request): View
    {
        return view('reports.calls', $this->report($request));
    }

    /** ดาวน์โหลดเป็น Excel — ช่วงวันที่และตัวกรองเดียวกับที่เห็นบนหน้าจอ */
    public function export(Request $request): BinaryFileResponse
    {
        $report = $this->report($request);
        ['from' => $from, 'to' => $to] = $report;

        $detail = [[
            'ครบกำหนดเช็คระยะ', 'ต้องโทร', 'ชื่อลูกค้า', 'เบอร์โทร', 'เบอร์สำรอง', 'รุ่น', 'เลขตัวถัง VIN', 'ครั้งที่',
            'ผลการโทร', 'รายละเอียด',
            // ปิดไว้ก่อน — 'จำนวนครั้งที่โทรในช่วงนี้', 'ลำดับผลการโทร',
            'บันทึกโดย', 'เวลาที่บันทึก',
        ]];

        foreach ($report['rows'] as $row) {
            $call = $row['appointment'];
            $last = $row['last'];

            $detail[] = [
                thai_date($row['service_due'], short: true),
                thai_date($row['call_from'], short: true).($row['recalled'] ? ' (นัดโทรซ้ำ)' : ''),
                $call->fullName(),
                phone_format($call->phone),
                phone_format($call->phone2),
                $call->model_name,
                $call->vin,
                $call->service_no,
                self::RESULT_LABELS[$row['result']],
                $last?->detail(),
                // ปิดไว้ก่อน — จำนวนครั้งที่โทรในช่วงนี้ / ลำดับผลการโทร
                // $row['logs']->count(),
                // $row['logs']->count() > 1 ? $row['logs']->map(fn (CallLog $log) => $log->resultLabel())->implode(' → ') : null,
                $last ? ($last->user?->name ?? '-') : null,
                $last ? thai_date($last->created_at, short: true).' '.$last->created_at->format('H:i').' น.' : null,
            ];
        }

        $summary = [['หัวข้อ', 'จำนวน']];
        foreach (self::RESULT_LABELS as $key => $label) {
            $summary[] = [$label, $report['counts'][$key]];
        }

        $byUser = [array_merge(['ผู้โทร', 'โทรทั้งหมด'], array_values(CallLog::RESULTS))];
        foreach ($report['byUser'] as $name => $counts) {
            $byUser[] = array_merge([$name, $counts['total']],
                array_map(fn (string $key) => $counts[$key] ?? 0, array_keys(CallLog::RESULTS)));
        }

        $range = $from->isSameDay($to)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d').'_'.$to->format('Y-m-d');

        return (new XlsxWriter)
            ->addSheet('รายการ', $detail, [16, 22, 32, 14, 14, 20, 21, 8, 16, 34, 18, 18])
            ->addSheet('สรุป', $summary, [20, 12])
            ->addSheet('แยกตามผู้โทร', $byUser, [24, 12, 12, 12, 18, 12])
            ->download('รายงานการโทร_'.$range.'.xlsx');
    }

    /** @return array<string, mixed> */
    private function report(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'result' => ['nullable', Rule::in(self::FILTERS)],
        ], [
            'from.date_format' => 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง',
            'to.date_format' => 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง',
        ]);

        // ส่วนใหญ่ดูวันต่อวัน ค่าตั้งต้นจึงเป็นวันนี้ถึงวันนี้
        $from = Carbon::parse($filters['from'] ?? now()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $result = $filters['result'] ?? 'all';
        $rows = $this->rows($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'result' => $result,
            'rows' => $rows->filter(fn (array $row) => match ($result) {
                'all' => true,
                'called' => $row['result'] !== 'pending',
                default => $row['result'] === $result,
            })->values(),
            'counts' => $this->counts($rows),
            'byUser' => $this->byUser($rows),
            'aheadDays' => CallListController::CALL_AHEAD_DAYS,
        ];
    }

    /**
     * หนึ่งแถวต่อหนึ่งคิวที่ต้องโทรในช่วงนี้
     *
     * @return Collection<int, array{appointment: Appointment, due: CarbonInterface, result: string, last: ?CallLog, logs: Collection<int, CallLog>}>
     */
    private function rows(Carbon $from, Carbon $to): Collection
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // คิวที่มีการโทรในช่วงนี้ — นับเป็นคิวที่ต้องโทรแน่ ๆ ไม่ว่าวันที่ต้องโทรจะเป็นวันไหน
        // whereHas ผ่านคิวทำให้ global scope ของยี่ห้อกรองให้ด้วย
        $logs = CallLog::active()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('appointment')
            ->with('user')
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->groupBy('appointment_id');

        $called = Appointment::with(['title', 'firstCallLog'])->whereKey($logs->keys())->get()
            ->map(function (Appointment $appointment) use ($logs) {
                $own = $logs[$appointment->id];

                return [
                    'appointment' => $appointment,
                    // วันที่ต้องโทร ณ ตอนโทรครั้งแรกในช่วงนี้ ไม่ใช่วันที่ถูกเลื่อนไปหลังโทร
                    'due' => $own->first()->due_date,
                    'result' => $own->last()->result,
                    'last' => $own->last(),
                    'logs' => $own,
                ];
            });

        return $called->concat($this->pending($end, $to, $logs->keys()))
            ->map(fn (array $row) => $row + $this->callDates($row))
            ->sortBy(fn (array $row) => [$row['call_from']->toDateString(), $row['appointment']->id])
            ->values();
    }

    /**
     * แยกวันครบกำหนดเช็คระยะ กับวันที่ต้องโทรจริง ให้ตรงกับหน้า "ต้องโทรหา"
     *
     * วันที่ต้องโทร ณ ตอนนั้น (due) ไม่ตรงกับวันครบกำหนด = เคยเลื่อนโทรไว้ ยึดวันที่เลื่อน
     * ไม่งั้นเริ่มโทรได้ตั้งแต่ก่อนครบกำหนด CALL_AHEAD_DAYS วัน
     *
     * @param  array{appointment: Appointment, due: CarbonInterface}  $row
     * @return array{service_due: CarbonInterface, call_from: CarbonInterface, recalled: bool}
     */
    private function callDates(array $row): array
    {
        $serviceDue = $row['appointment']->serviceDueDate();
        $recalled = ! $row['due']->isSameDay($serviceDue);

        return [
            'service_due' => $serviceDue,
            'call_from' => $recalled ? $row['due'] : $serviceDue->copy()->subDays(CallListController::CALL_AHEAD_DAYS),
            'recalled' => $recalled,
        ];
    }

    /**
     * คิวที่อยู่ในรายการต้องโทรตอนสิ้นสุดช่วง แต่ไม่มีการโทรเลยในช่วงนี้
     *
     * ดูย้อนหลังได้ถูกต้องเพราะ call_logs เก็บวันที่ต้องโทรก่อนเปลี่ยนไว้ — ประวัติแถวแรก
     * หลังสิ้นสุดช่วงบอกได้ว่าตอนนั้นคิวนี้ต้องโทรวันไหน ถ้าไม่มีเลยแปลว่ายังไม่เปลี่ยนตั้งแต่นั้น
     *
     * @param  Collection<int, int>  $calledIds
     * @return Collection<int, array<string, mixed>>
     */
    private function pending(Carbon $end, Carbon $to, Collection $calledIds): Collection
    {
        $horizon = $to->copy()->addDays(CallListController::CALL_AHEAD_DAYS);
        $logsAfter = fn ($q) => $q->active()->where('created_at', '>', $end);

        $query = Appointment::with(['title', 'firstCallLog', 'callLogs' => fn ($q) => $logsAfter($q)->orderBy('created_at')->orderBy('id')])
            ->whereKeyNot($calledIds)
            ->where(fn (Builder $q) => $q->whereNull('created_at')->orWhere('created_at', '<=', $end))
            // คิวที่วันที่ต้องโทรตอนนี้ยังอยู่ในช่วง หรือถูกแก้ไปหลังจากนั้น (ต้องไปดูวันเดิมจากประวัติ)
            ->where(fn (Builder $q) => $q->where('call_due_date', '<=', $horizon->toDateString())
                ->orWhereHas('callLogs', $logsAfter));

        // ยังไม่ปิดคิว ณ สิ้นสุดช่วง — ปิดทีหลังก็ยังนับว่าตอนนั้นค้างอยู่
        foreach (['confirmed_at', 'completed_at', 'declined_at'] as $column) {
            $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, '>', $end));
        }

        return $query->get()
            ->map(fn (Appointment $appointment) => [
                'appointment' => $appointment,
                'due' => $appointment->callLogs->first()?->due_date ?? $appointment->callDueDate(),
                'result' => 'pending',
                'last' => null,
                'logs' => collect(),
            ])
            ->filter(fn (array $row) => $row['due']->lte($horizon));
    }

    /** @return array<string, int> */
    private function counts(Collection $rows): array
    {
        $byResult = $rows->countBy('result');

        return collect(self::FILTERS)->mapWithKeys(fn (string $key) => [$key => match ($key) {
            'all' => $rows->count(),
            'called' => $rows->count() - ($byResult['pending'] ?? 0),
            default => $byResult[$key] ?? 0,
        }])->all();
    }

    /**
     * ผลงานแยกตามผู้โทร — นับทุกครั้งที่กดบันทึก (คิวเดียวโทรสองรอบนับสอง)
     *
     * @return Collection<string, array<string, int>>
     */
    private function byUser(Collection $rows): Collection
    {
        return $rows->flatMap(fn (array $row) => $row['logs'])
            ->groupBy(fn (CallLog $log) => $log->user?->name ?? 'ไม่ทราบ (ข้อมูลเก่า)')
            ->map(fn (Collection $logs) => ['total' => $logs->count()] + $logs->countBy('result')->all())
            ->sortByDesc('total');
    }
}
