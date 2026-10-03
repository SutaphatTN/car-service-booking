<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** กรองตามยี่ห้อที่กำลังทำงานอยู่ให้อัตโนมัติ และเติม brand_id ให้ตอนสร้าง */
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'title_id', 'first_name', 'last_name', 'phone', 'phone2', 'vin', 'delivered_at',
        'mileage', 'appointment_at', 'created_by',
        'salecar_id', 'service_no', 'model_id', 'model_name', 'confirmed_at', 'completed_at', 'source_missing_at',
        'unreachable_at', 'recall_reason', 'recall_at', 'unreachable_count',
        'declined_at', 'declined_reason', 'declined_note',
    ];

    /**
     * เหตุผลที่ลูกค้าไม่เข้าเช็คระยะครั้งนี้ → ชื่อที่แสดงบนหน้าจอ
     *
     * 'skip' อย่างเดียวที่ยังไปต่อ — ครั้งนี้ไม่เข้าแต่ยังเป็นลูกค้าอยู่ ระบบจึงสร้าง
     * คิวครั้งถัดไปให้เหมือนตอนปิดงาน ที่เหลือแปลว่ารถคันนี้จบตารางเช็คระยะกับเราแล้ว
     */
    public const DECLINE_REASONS = [
        'skip' => 'ครั้งนี้ไม่เข้า ข้ามไปครั้งหน้า',
        'other_shop' => 'ใช้บริการศูนย์อื่น',
        'sold' => 'ขายรถ/เปลี่ยนเจ้าของแล้ว',
        'no_service' => 'ไม่ประสงค์รับบริการ ไม่ต้องโทรอีก',
        'other' => 'อื่น ๆ',
    ];

    /** เหตุผลที่เลื่อนวันโทร → ชื่อที่แสดงบนหน้าจอ */
    public const RECALL_REASONS = [
        'unreachable' => 'ติดต่อไม่ได้',
        'not_ready' => 'ลูกค้ายังไม่สะดวก',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'delivered_at' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'source_missing_at' => 'datetime',
            'unreachable_at' => 'datetime',
            'recall_at' => 'date',
            'unreachable_count' => 'integer',
            'declined_at' => 'datetime',
            'mileage' => 'integer',
            'service_no' => 'integer',
        ];
    }

    /** ดึงมาจากฐานข้อมูลฝ่ายขาย ไม่ได้คีย์เองจากหน้าเว็บ */
    public function isFromSales(): bool
    {
        return $this->salecar_id !== null;
    }

    /** ยังต้องโทรหาลูกค้าเพื่อยืนยันวันเวลา */
    public function needsCall(): bool
    {
        return $this->confirmed_at === null
            && $this->completed_at === null
            && $this->declined_at === null;
    }

    /** เลื่อนวันโทรไว้และยังรอโทรซ้ำอยู่ — ไม่ว่าจะเพราะโทรไม่ติดหรือลูกค้าขอเลื่อนเอง */
    public function isPostponed(): bool
    {
        return $this->unreachable_at !== null && $this->needsCall();
    }

    /** โทรไม่ติดและยังรอโทรซ้ำอยู่ (ข้อมูลเก่าที่ยังไม่มีเหตุผลกำกับ นับเป็นโทรไม่ติดตามความหมายเดิมของปุ่ม) */
    public function isUnreachable(): bool
    {
        return $this->isPostponed() && $this->recall_reason !== 'not_ready';
    }

    /** โทรติดแล้ว แต่ลูกค้ายังไม่พร้อมนัด ขอให้โทรใหม่ทีหลัง */
    public function isNotReady(): bool
    {
        return $this->isPostponed() && $this->recall_reason === 'not_ready';
    }

    /** ลูกค้าบอกว่าไม่เข้าเช็คระยะครั้งนี้ — ปิดคิวโดยไม่ได้เช็ค */
    public function isDeclined(): bool
    {
        return $this->declined_at !== null && $this->completed_at === null;
    }

    /** ชื่อเหตุผลที่ปฏิเสธสำหรับแสดงบนหน้าจอ */
    public function declineReasonLabel(): ?string
    {
        return self::DECLINE_REASONS[$this->declined_reason] ?? null;
    }

    /**
     * วันที่ต้องโทรจริง — เลื่อนนัดโทรไว้ก็ยึดวันที่เลื่อน ไม่ใช่วันครบกำหนดตามตารางเช็คระยะ
     * (ตรงกับคอลัมน์คำนวณ call_due_date ที่ใช้กรอง/เรียงในฐานข้อมูล)
     */
    public function callDueDate(): CarbonInterface
    {
        return $this->recall_at ?? $this->appointment_at->copy()->startOfDay();
    }

    /** เลยวันที่ต้องโทรมาแล้ว */
    public function isCallOverdue(): bool
    {
        return $this->callDueDate()->lt(now()->startOfDay());
    }

    /** สถานะสำหรับแสดงบนหน้าจอ */
    public function status(): string
    {
        return self::statusLabel($this->statusKey());
    }

    /**
     * ชื่อสถานะของคีย์ที่กำหนด — ใช้ทั้งบนป้ายในตารางและบนแถบกรองสถานะ
     * 'open' กับ 'all' ไม่ใช่สถานะของแถวไหน เป็นตัวเลือกบนแถบกรองอย่างเดียว
     */
    public static function statusLabel(string $key): string
    {
        return match ($key) {
            'done' => 'เช็คแล้ว',
            'overdue' => 'ครบกำหนดเข้า',
            'booked' => 'ยืนยันนัดหมาย',
            'unreachable' => 'ติดต่อไม่ได้',
            'not_ready' => 'ยังไม่สะดวก',
            'declined' => 'ไม่เข้าเช็ค',
            'waiting' => 'รอโทร',
            'open' => 'ที่ยังไม่ปิดงาน',
            default => 'ทั้งหมด',
        };
    }

    /**
     * กรองตามสถานะที่เห็นบนหน้าจอ — ต้องให้ผลตรงกับ statusKey() เสมอ
     * (มีเทสต์เทียบผลของสโคปนี้กับ statusKey() ทีละสถานะไว้กันตรรกะสองฝั่งเพี้ยนจากกัน)
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $endOfToday = now()->endOfDay();

        // คิวที่ยังไม่ปิด — ปิดได้สองทาง เช็คเสร็จ (completed_at) หรือลูกค้าไม่เข้า (declined_at)
        $open = fn (Builder $q) => $q->whereNull('completed_at')->whereNull('declined_at');

        // คิวที่ยังไม่ถึงวันนัดและยังไม่ปิด — ฐานร่วมของ booked / unreachable / not_ready / waiting
        $upcoming = fn (Builder $q) => $open($q)->where('appointment_at', '>', $endOfToday);

        // เลื่อนวันโทรไว้แล้วแต่ยังไม่ได้นัด — ต่างกันแค่เหตุผล
        $postponed = fn (Builder $q) => $upcoming($q)->whereNull('confirmed_at')->whereNotNull('unreachable_at');

        return match ($status) {
            'done' => $query->whereNotNull('completed_at'),
            'declined' => $query->whereNull('completed_at')->whereNotNull('declined_at'),
            'overdue' => $open($query)->where('appointment_at', '<=', $endOfToday),
            'booked' => $upcoming($query)->whereNotNull('confirmed_at'),
            // ข้อมูลเก่าไม่มี recall_reason กำกับ นับเป็นโทรไม่ติด ให้ตรงกับ statusKey()
            'unreachable' => $postponed($query)->where(
                fn (Builder $q) => $q->whereNull('recall_reason')->orWhere('recall_reason', '!=', 'not_ready')
            ),
            'not_ready' => $postponed($query)->where('recall_reason', 'not_ready'),
            'waiting' => $upcoming($query)->whereNull('confirmed_at')->whereNull('unreachable_at'),
            'open' => $open($query),
            default => $query,
        };
    }

    /** ชื่อสถานะแบบสั้น ใช้เป็นชื่อคลาส CSS ด้วย */
    public function statusKey(): string
    {
        return match (true) {
            $this->completed_at !== null => 'done',
            // ลูกค้าไม่เข้าเช็ค = ปิดคิวไปแล้วเหมือนกัน ต้องมาก่อน overdue ไม่งั้นพอเลยวัน
            // ครบกำหนดจะกลายเป็นงานค้างสีแดงทั้งที่ตกลงกับลูกค้าเรียบร้อยแล้ว
            $this->declined_at !== null => 'declined',
            // ถึงวันนัดแล้วแต่ยังไม่ปิดงาน — เร่งด่วนสุดในบรรดาคิวที่ยังไม่จบ
            // จึงมาก่อนสถานะอื่น ไม่ว่าจะยืนยันนัดไปแล้วหรือยังไม่ได้โทรเลยก็ตาม
            // นับทั้งวัน ไม่ใช่เลยเวลานัด — นัดบ่ายสามก็ขึ้นแดงตั้งแต่เช้าให้เห็นว่าเป็นงานของวันนี้
            $this->appointment_at->lte(now()->endOfDay()) => 'overdue',
            $this->confirmed_at !== null => 'booked',
            // เลื่อนวันโทรไว้แล้วรอโทรซ้ำ — ยังไม่ได้นัด แต่ไม่ใช่ "รอโทร" เฉย ๆ ต้องแยกให้เห็น
            // และต้องแยกสองเหตุผลออกจากกัน โทรไม่ติดกับลูกค้าขอเลื่อนเองคนละเรื่องกัน
            $this->unreachable_at !== null => $this->recall_reason === 'not_ready' ? 'not_ready' : 'unreachable',
            default => 'waiting',
        };
    }

    /**
     * ค้นหาจากชื่อ นามสกุล เบอร์โทร (ทั้งสองเบอร์) เลขตัวถัง และรุ่นรถ
     *
     * เบอร์ในฐานข้อมูลเก็บเป็นตัวเลขล้วน พิมพ์ "081-958" มาก็ต้องหาเจอ
     * จึงตัดอักขระที่ไม่ใช่ตัวเลขออกก่อนค้นเฉพาะคอลัมน์เบอร์
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // % และ _ เป็นอักขระพิเศษของ LIKE ต้อง escape ไม่งั้นพิมพ์ % แล้วได้ทุกแถว
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                // พิมพ์ชื่อพร้อมนามสกุลมาทีเดียวก็ต้องเจอ
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhere('vin', 'like', $like)
                ->orWhere('model_name', 'like', $like);

            if ($digits !== '') {
                $q->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhere('phone2', 'like', '%'.$digits.'%');
            }
        });
    }

    /** คิวที่ยังต้องโทร และถึงกำหนดภายใน $days วันข้างหน้า */
    /** คิวที่ยังไม่ได้ยืนยันวันเวลาและยังไม่ได้เช็ค — ฐานของทุกมุมมองในหน้า "ต้องโทรหา" */
    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at')->whereNull('completed_at')->whereNull('declined_at');
    }

    /** มุมมองปกติ — ถึงกำหนดภายใน N วันข้างหน้า (รวมที่เลยกำหนดมาแล้วทั้งหมด) */
    public function scopeNeedingCall(Builder $query, int $days): Builder
    {
        return $query->unhandled()
            ->where('call_due_date', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * มุมมองรายเดือน — คิวของเดือนที่เลือก บวกกับคิวที่เลยกำหนดไปแล้วทุกใบ
     *
     * คิวค้างเก่าต้องติดมาด้วยเสมอ ไม่งั้นพอเลื่อนไปดูเดือนหน้าแล้วลูกค้าที่ยัง
     * ไม่ได้โทรจากเดือนก่อนจะหายไปจากสายตา (ใช้ overdueFirst() ดันขึ้นบนสุด)
     */
    public function scopeNeedingCallInMonth(Builder $query, CarbonInterface $month): Builder
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return $query->unhandled()->where(
            fn (Builder $q) => $q->whereBetween('call_due_date', [$start, $end])
                ->orWhere('call_due_date', '<', now()->toDateString())
        );
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ประวัติการโทรของคิวนี้ */
    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    /** การโทรครั้งแรกสุดของคิว (นับรวมที่ยกเลิกเพราะกดผิดด้วย) — ใช้หาวันครบกำหนดเดิม */
    public function firstCallLog(): HasOne
    {
        return $this->hasOne(CallLog::class)->oldestOfMany();
    }

    /**
     * วันครบกำหนดเช็คระยะตามตาราง
     *
     * ตอนยืนยันนัด appointment_at ถูกเขียนทับเป็นวันนัดจริง วันครบกำหนดเดิมจึงต้องเอาจาก
     * ประวัติการโทรครั้งแรก (ก่อนครั้งแรกยังไม่เคยเลื่อนโทร วันที่ต้องโทรตอนนั้นคือวันครบกำหนด)
     */
    public function serviceDueDate(): CarbonInterface
    {
        if ($this->confirmed_at !== null && $this->firstCallLog) {
            return $this->firstCallLog->due_date;
        }

        return $this->appointment_at->copy()->startOfDay();
    }

    /** เรียงตามวันและเวลานัด */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('appointment_at')->orderBy('id');
    }

    /** เรียงตามวันที่ต้องโทรจริง — ใช้กับหน้า "ต้องโทรหา" ที่คิวเลื่อนวันโทรได้ */
    public function scopeCallOrdered(Builder $query): Builder
    {
        return $query->orderBy('call_due_date')->orderBy('appointment_at')->orderBy('id');
    }

    /** เรียงเหมือน callOrdered() แต่ดันคิวที่เลยวันต้องโทรแล้วขึ้นไปไว้บนสุด */
    public function scopeOverdueFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(call_due_date < ?) desc', [now()->toDateString()])
            ->callOrdered();
    }

    /** ชื่อเต็มพร้อมคำนำหน้า เช่น "นายสมชาย ใจดี" */
    public function fullName(): string
    {
        return trim(($this->title?->name ?? '').$this->first_name.' '.$this->last_name);
    }
}
