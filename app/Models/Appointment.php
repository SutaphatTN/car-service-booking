<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'title_id', 'first_name', 'last_name', 'phone', 'mileage', 'appointment_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'mileage' => 'integer',
        ];
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
