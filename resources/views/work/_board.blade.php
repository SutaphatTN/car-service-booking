{{--
    ตารางเวลาแบบตารางเรียน — ใช้ทั้งมุมมองรายวัน (แถว = ช่าง) และรายคน (แถว = วัน)
    $rows: label, link, note, jobs, technician_id, date, can_add (+ weekday, weekend ของมุมมองรายคน)
--}}
@use('App\Support\WorkSchedule')

@php
    $span = $rangeEnd - $rangeStart;
    $pos = fn (int $minutes) => round(($minutes - $rangeStart) / $span * 100, 4);
    $hours = fn (int $minutes) => rtrim(rtrim(number_format($minutes / 60, 2), '0'), '.');
@endphp

<div class="work-scroll">
    <div class="work-board" style="--hours: {{ $span / 60 }}">
        <div class="work-row work-head">
            <div class="work-name">{{ $headLabel }}</div>
            <div class="work-track">
                @for ($m = $rangeStart; $m <= $rangeEnd; $m += 60)
                    <span class="work-hour" style="left: {{ $pos($m) }}%">{{ WorkSchedule::label($m) }}</span>
                @endfor
            </div>
        </div>

        @foreach ($rows as $row)
            <div @class(['work-row', 'is-weekend' => $row['weekend'] ?? false])>
                <div class="work-name">
                    <a href="{{ $row['link'] }}" class="work-name-link" title="{{ $linkTitle }}">
                        @isset($row['weekday'])
                            <span class="work-weekday">{{ $row['weekday'] }}</span>
                        @endisset
                        <b>{{ $row['label'] }}</b>
                    </a>
                    <div class="sub">
                        @if ($row['note'])
                            {{ $row['note'] }} ·
                        @endif
                        @if ($row['jobs']->isEmpty())
                            ว่าง
                        @else
                            {{ $row['jobs']->count() }} งาน · {{ $hours($row['jobs']->sum(fn ($job) => $job->durationMinutes())) }} ชม.
                        @endif
                    </div>
                </div>
                <div @class(['work-track', 'can-add' => $row['can_add']])
                     data-technician="{{ $row['technician_id'] }}" data-date="{{ $row['date'] }}"
                     @if ($row['can_add']) title="คลิกช่องว่างเพื่อลงงาน" @endif>
                    @foreach ($row['jobs'] as $job)
                        <button type="button" class="job job-{{ $job->job_type }}"
                                style="left: {{ $pos($job->startMinutes()) }}%; width: {{ $pos($job->endMinutes()) - $pos($job->startMinutes()) }}%"
                                title="{{ $job->job_type }} · {{ $job->startLabel() }}–{{ $job->endLabel() }} น.{{ $job->license_plate ? ' · '.$job->license_plate : '' }}{{ $job->note ? ' · '.$job->note : '' }}"
                                onclick="event.stopPropagation(); openJob({{ $job->id }})">
                            <b>{{ $job->job_type }}</b>
                            <span>{{ $job->startLabel() }}–{{ $job->endLabel() }}</span>
                            @if ($job->license_plate)
                                <span>{{ $job->license_plate }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
