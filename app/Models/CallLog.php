<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ประวัติการโทรหาลูกค้า — หนึ่งแถวต่อการกดบันทึกผลหนึ่งครั้งในหน้า "ต้องโทรหา"
 *
 * appointments เก็บแค่สถานะล่าสุด กดทับแล้วของเดิมหายไป รายงานการโทรย้อนหลังจึงอ่านจากตารางนี้
 * ไม่มี brand_id ของตัวเอง — กรองยี่ห้อผ่านคิวที่โทร (whereHas('appointment'))
 */
class CallLog extends Model
{
    /** บันทึกแล้วไม่แก้ไข นอกจากติดธงยกเลิกตอนกดผิด */
    public const UPDATED_AT = null;

    protected $fillable = [
        'appointment_id', 'user_id', 'result', 'due_date', 'booked_at', 'recall_at',
        'declined_reason', 'note', 'cancelled_at',
    ];

    /** ผลการโทร → ชื่อที่แสดงบนหน้าจอ */
    public const RESULTS = [
        'confirmed' => 'ได้นัด',
        'unreachable' => 'โทรไม่ติด',
        'not_ready' => 'ลูกค้ายังไม่สะดวก',
        'declined' => 'ไม่เข้าเช็ค',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'booked_at' => 'datetime',
            'recall_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * บันทึกผลการโทรของคิว — ต้องเรียกก่อนแก้คิว จะได้เก็บวันที่ต้องโทรก่อนถูกเลื่อน
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(Appointment $appointment, string $result, array $attributes = []): self
    {
        return self::create($attributes + [
            'appointment_id' => $appointment->id,
            'user_id' => auth()->id(),
            'result' => $result,
            'due_date' => $appointment->callDueDate()->toDateString(),
        ]);
    }

    /**
     * กดผิด — ยกเลิกผลล่าสุดที่ยังนับอยู่ของคิวนี้ (เฉพาะผลประเภทที่กำหนด) ไม่ลบแถวทิ้ง
     *
     * @param  list<string>  $results
     */
    public static function cancelLatest(Appointment $appointment, array $results): void
    {
        self::active()
            ->where('appointment_id', $appointment->id)
            ->whereIn('result', $results)
            ->latest('id')
            ->first()
            ?->update(['cancelled_at' => now()]);
    }

    /** ยังนับอยู่ — ไม่ได้ถูกยกเลิกเพราะกดผิด */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    /** รายละเอียดของผล เช่น "นัด 7 ต.ค. 69 10:00 น." / "โทรซ้ำ 9 ต.ค. 69" — ใช้ทั้งบนหน้าจอและใน Excel */
    public function detail(): string
    {
        return match ($this->result) {
            'confirmed' => 'นัด '.thai_date($this->booked_at, short: true).' '.$this->booked_at->format('H:i').' น.',
            'declined' => (Appointment::DECLINE_REASONS[$this->declined_reason] ?? '-')
                .($this->note ? ' · '.$this->note : ''),
            default => 'โทรซ้ำ '.($this->recall_at ? thai_date($this->recall_at, short: true) : '-'),
        };
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
