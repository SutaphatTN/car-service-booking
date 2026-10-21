@extends('layouts.app')

@section('title', 'ปฏิทินงานช่าง')

@php
    // เลื่อนวันบนตารางแล้วช่วงของสรุปต้องอยู่เหมือนเดิม และกลับกัน
    $keepRange = request()->only(['from', 'to']);
    $dayUrl = fn ($d) => route('work.index', ['date' => $d->toDateString()] + $keepRange);
    $rangeUrl = fn ($from, $to) => route('work.index', array_filter([
        'date' => request('date'),
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]));
    $hours = fn (int $minutes) => rtrim(rtrim(number_format($minutes / 60, 2), '0'), '.');

    // ช่วงที่ใช้บ่อย — เหมือนหน้ารายคน
    $span = $from->diffInDays($to) + 1;
    $quickRanges = [
        'สัปดาห์นี้' => [today()->startOfWeek(), today()->endOfWeek()],
        'สัปดาห์ที่แล้ว' => [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()],
        'เดือนนี้' => [today()->startOfMonth(), today()->endOfMonth()],
        'เดือนที่แล้ว' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>ปฏิทินงานช่าง</h2>
            <p class="muted">
                {{ thai_date($date) }}
                @if ($date->isToday()) · วันนี้ @endif
                · งาน {{ baht($summary['jobs']) }} รายการ
                @if ($summary['minutes']) · รวม {{ $hours($summary['minutes']) }} ชม. @endif
            </p>
        </div>
        <div class="page-tools">
            @include('work._tabs')
            <button type="button" class="primary" onclick="openJob()">+ เพิ่มงานช่าง</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    {{-- สรุปงานทุกช่างตามช่วงวันที่ — ดูบนจอ หรือดาวน์โหลด Excel ไปคิดค่าคอม --}}
    @if ($technicians->isNotEmpty())
        <div class="card">
            <form method="GET" action="{{ route('work.index') }}">
                @if (request('date'))
                    <input type="hidden" name="date" value="{{ request('date') }}">
                @endif
                <div class="filters">
                    <label class="field">ตั้งแต่วันที่
                        <input type="date" name="from" value="{{ $from->toDateString() }}" required>
                    </label>

                    <label class="field">ถึงวันที่
                        <input type="date" name="to" value="{{ $to->toDateString() }}" required>
                    </label>

                    <button type="submit" class="primary">ดูสรุป</button>
                    <a class="button ghost" download
                       href="{{ route('work.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">ดาวน์โหลด Excel</a>
                </div>
            </form>

            <div class="quick-ranges">
                <a class="cal-nav" title="ช่วงก่อนหน้า"
                   href="{{ $rangeUrl($from->copy()->subDays($span), $from->copy()->subDay()) }}">&lsaquo;</a>
                <a class="cal-nav" title="ช่วงถัดไป"
                   href="{{ $rangeUrl($to->copy()->addDay(), $to->copy()->addDays($span)) }}">&rsaquo;</a>
                <span class="muted">ช่วงที่ใช้บ่อย</span>
                @foreach ($quickRanges as $label => [$start, $end])
                    <a @class(['chip-link', 'active' => $from->isSameDay($start) && $to->isSameDay($end)])
                       href="{{ $rangeUrl($start, $end) }}">{{ $label }}</a>
                @endforeach
            </div>

            {{-- ตารางสรุปรายช่างบนจอ — ปิดไว้ก่อน (ดูจาก Excel แทน) เปิดใช้ได้โดยเอาคอมเมนต์นี้ออก
            <div class="table-scroll" style="margin-top:14px">
                <table class="work-summary">
                    <thead>
                    <tr>
                        <th>ช่าง · {{ thai_date($from, short: true) }} – {{ thai_date($to, short: true) }}</th>
                        @foreach (App\Models\TechnicianJob::TYPES as $type)
                            <th class="right" style="width:70px"><span class="job-tag job-{{ $type }}">{{ $type }}</span></th>
                        @endforeach
                        <th class="right" style="width:90px">จำนวนงาน</th>
                        <th class="right" style="width:90px">ชม. รวม</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($report as $row)
                        <tr>
                            <td>
                                <a href="{{ route('work.person', ['technician' => $row['technician']->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                   title="ดูงานของช่างคนนี้ในช่วงนี้"><b>{{ $row['technician']->name }}</b></a>
                                @if ($row['technician']->trashed()) <span class="sub">(ลบออกแล้ว)</span> @endif
                            </td>
                            @foreach ($row['types'] as $count)
                                <td class="right">{{ $count ? baht($count) : '-' }}</td>
                            @endforeach
                            <td class="right"><b>{{ baht($row['jobs']) }}</b></td>
                            <td class="right"><b>{{ $row['minutes'] ? $hours($row['minutes']) : '-' }}</b></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td><b>รวม</b></td>
                        @foreach (App\Models\TechnicianJob::TYPES as $type)
                            <td class="right"><b>{{ baht($report->sum(fn ($row) => $row['types'][$type])) }}</b></td>
                        @endforeach
                        <td class="right"><b>{{ baht($report->sum('jobs')) }}</b></td>
                        <td class="right"><b>{{ $hours($report->sum('minutes')) ?: '-' }}</b></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
            --}}
        </div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="month-bar">
            <a class="cal-nav" title="วันก่อนหน้า" href="{{ $dayUrl($date->copy()->subDay()) }}">&lsaquo;</a>

            <form method="GET" action="{{ route('work.index') }}" class="month-jump">
                <input type="date" name="date" id="dayJump" value="{{ $date->toDateString() }}"
                       aria-label="เลือกวันที่" required>
                @foreach ($keepRange as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <noscript><button type="submit" class="ghost">ไป</button></noscript>
            </form>

            <a class="cal-nav" title="วันถัดไป" href="{{ $dayUrl($date->copy()->addDay()) }}">&rsaquo;</a>

            @unless ($date->isToday())
                <a class="chip-link" href="{{ $dayUrl(today()) }}">วันนี้</a>
            @endunless

            <div class="work-legend">
                @foreach ($summary['types'] as $type => $count)
                    <span class="job-tag job-{{ $type }}">{{ $type }} <b>{{ baht($count) }}</b></span>
                @endforeach
            </div>
        </div>

        @if ($technicians->isEmpty())
            <p class="empty" style="margin:0;padding:34px 18px;text-align:center">
                ยังไม่มีข้อมูลช่างของยี่ห้อนี้
                @if (auth()->user()->canManageTechnicians())
                    — <a href="{{ route('technicians.index') }}">เพิ่มช่าง</a>
                @else
                    — ให้ Admin เพิ่มรายชื่อช่างก่อน
                @endif
            </p>
        @else
            @include('work._board', [
                'headLabel' => 'ช่าง',
                'linkTitle' => 'ดูงานของช่างคนนี้ทั้งสัปดาห์',
            ])
        @endif
    </div>

    @unless ($canAdd)
        <p class="muted" style="font-size:13px">วันนี้เป็นวันที่ผ่านมาแล้ว ลงงานย้อนหลังได้เฉพาะ Admin</p>
    @endunless

    @include('work._dialog')
@endsection

@push('scripts')
    <script>
        // เปลี่ยนวันในช่องเลือกวันแล้วไปเลย
        const dayJump = document.getElementById('dayJump');

        dayJump.addEventListener('change', () => {
            if (dayJump.value) dayJump.form.submit();
        });
    </script>
@endpush
