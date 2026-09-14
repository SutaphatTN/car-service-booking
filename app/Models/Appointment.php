<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** กรองตามยี่ห้อที่กำลังทำงานอยู่ให้อัตโนมัติ และเติม brand_id ให้ตอนสร้าง */
    use BelongsToBrand;

    protected $fillable = [
        'brand_id', 'title_id', 'first_name', 'last_name', 'phone', 'phone2', 'vin', 'delivered_at',
        'mileage', 'appointment_at', 'created_by',
        'salecar_id', 'service_no', 'model_id', 'model_name', 'confirmed_at', 'completed_at', 'source_missing_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'delivered_at' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'source_missing_at' => 'datetime',
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
        return $this->confirmed_at === null && $this->completed_at === null;
    }

    /** สถานะสำหรับแสดงบนหน้าจอ */
    public function status(): string
    {
        return match ($this->statusKey()) {
            'done' => 'เช็คแล้ว',
            'booked' => 'นัดแล้ว',
            default => 'รอโทร',
        };
    }

    /** ชื่อสถานะแบบสั้น ใช้เป็นชื่อคลาส CSS ด้วย */
    public function statusKey(): string
    {
        return match (true) {
            $this->completed_at !== null => 'done',
            $this->confirmed_at !== null => 'booked',
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
    public function scopeNeedingCall(Builder $query, int $days): Builder
    {
        return $query->whereNull('confirmed_at')
            ->whereNull('completed_at')
            ->where('appointment_at', '<=', now()->addDays($days)->endOfDay());
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** เรียงตามวันและเวลานัด */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('appointment_at')->orderBy('id');
    }

    /** ชื่อเต็มพร้อมคำนำหน้า เช่น "นายสมชาย ใจดี" */
    public function fullName(): string
    {
        return trim(($this->title?->name ?? '').$this->first_name.' '.$this->last_name);
    }
}
