<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** งานช่าง — ช่างคนไหนทำงานอะไร วันไหน กี่โมงถึงกี่โมง */
class TechnicianJob extends Model
{
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'technician_id', 'work_date', 'start_time', 'end_time',
        'job_type', 'license_plate', 'note', 'created_by',
    ];

    /** ประเภทงาน — เพิ่มประเภทใหม่ต้องแก้ ENUM ของคอลัมน์ job_type ในฐานข้อมูลด้วย */
    public const TYPES = ['PM', 'GR', 'PDI', 'PDS'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    /** ช่างที่ถูกลบไปแล้วก็ยังต้องเห็นชื่อในงานย้อนหลัง */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** เวลาแบบ "08:30" (คอลัมน์ TIME ได้มาเป็น "08:30:00") */
    public function startLabel(): string
    {
        return substr($this->start_time, 0, 5);
    }

    public function endLabel(): string
    {
        return substr($this->end_time, 0, 5);
    }

    /** นาทีนับจากเที่ยงคืน ใช้วางตำแหน่งบนปฏิทิน */
    public function startMinutes(): int
    {
        return self::toMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::toMinutes($this->end_time);
    }

    public function durationMinutes(): int
    {
        return $this->endMinutes() - $this->startMinutes();
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * ใครแก้ไข/ลบงานนี้ได้ — admin ได้ทุกงาน
     * คนอื่นได้เฉพาะงานที่ตัวเองลงไว้ และต้องยังไม่ใช่งานย้อนหลัง (กันแก้ย้อนหลังเพื่อค่าคอม)
     */
    public function editableBy(User $user): bool
    {
        if ($user->canBackdateWork()) {
            return true;
        }

        return $this->created_by === $user->id && ! $this->work_date->lt(today());
    }

    /** งานของช่างคนนี้ในวันนั้นที่เวลาทับกับช่วงที่ให้มา (ไม่นับงาน $exceptId ตอนแก้ไข) */
    public function scopeOverlapping(Builder $query, int $technicianId, string $date, string $start, string $end, ?int $exceptId = null): Builder
    {
        // คอลัมน์ TIME เทียบเป็นข้อความ "10:00:00" > "10:00" จึงต้องเติมวินาทีให้ยาวเท่ากันก่อน
        $seconds = fn (string $time) => strlen($time) === 5 ? $time.':00' : $time;

        return $query->where('technician_id', $technicianId)
            ->whereDate('work_date', $date)
            // ทับกันเมื่อเริ่มก่อนอีกงานจบ และจบหลังอีกงานเริ่ม — ต่อกันพอดี (จบ 10:00 เริ่ม 10:00) ไม่นับว่าทับ
            ->where('start_time', '<', $seconds($end))
            ->where('end_time', '>', $seconds($start))
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId));
    }
}
