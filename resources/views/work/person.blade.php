@extends('layouts.app')

@section('title', 'งานช่างรายคน')

@php
    $hours = fn (int $minutes) => rtrim(rtrim(number_format($minutes / 60, 2), '0'), '.');
    $personUrl = fn ($from, $to) => route('work.person', array_filter([
        'technician' => $technician?->id,
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]));

    // ช่วงที่ใช้บ่อย — เลื่อนทีละสัปดาห์ได้จากปุ่ม ‹ ›
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
            <h2>งานช่างรายคน</h2>
            <p class="muted">
                @if ($technician)
                    {{ $technician->name }} · {{ thai_date($from, short: true) }} – {{ thai_date($to, short: true) }}
                    · งาน {{ baht($summary['jobs']) }} รายการ
                    @if ($summary['minutes']) · รวม {{ $hours($summary['minutes']) }} ชม. @endif
                @else
                    เลือกช่างและช่วงวันที่ที่ต้องการดู
                @endif
            </p>
        </div>
        <div class="page-tools">
            @include('work._tabs')
            @if ($technician && ! $technician->trashed())
                <button type="button" class="primary" onclick="openJob(null, { technician: '{{ $technician->id }}' })">+ เพิ่มงานช่าง</button>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    @if ($technicians->isEmpty())
        <div class="card">
            <p class="empty" style="margin:0;text-align:center">
                ยังไม่มีข้อมูลช่างของยี่ห้อนี้
                @if (auth()->user()->canManageTechnicians())
                    — <a href="{{ route('technicians.index') }}">เพิ่มช่าง</a>
                @else
                    — ให้ Admin เพิ่มรายชื่อช่างก่อน
                @endif
            </p>
        </div>
    @else
        <div class="card">
            <form method="GET" action="{{ route('work.person') }}">
                <div class="filters">
                    <label class="field">ช่าง
                        <select name="technician" required>
                            @foreach ($technicians as $option)
                                <option value="{{ $option->id }}" @selected($technician?->id === $option->id)>
                                    {{ $option->name }}{{ $option->trashed() ? ' (ลบออกแล้ว)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">ตั้งแต่วันที่
                        <input type="date" name="from" value="{{ $from->toDateString() }}" required>
                    </label>

                    <label class="field">ถึงวันที่
                        <input type="date" name="to" value="{{ $to->toDateString() }}" required>
                    </label>

                    <button type="submit" class="primary">ดูงาน</button>
                    @if ($technician)
                        <a class="button ghost" download
                           href="{{ route('work.person.export', ['technician' => $technician->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">ดาวน์โหลด Excel</a>
                    @endif
                </div>
            </form>

            <div class="quick-ranges">
                <a class="cal-nav" title="ช่วงก่อนหน้า"
                   href="{{ $personUrl($from->copy()->subDays($span), $from->copy()->subDay()) }}">&lsaquo;</a>
                <a class="cal-nav" title="ช่วงถัดไป"
                   href="{{ $personUrl($to->copy()->addDay(), $to->copy()->addDays($span)) }}">&rsaquo;</a>
                <span class="muted">ช่วงที่ใช้บ่อย</span>
                @foreach ($quickRanges as $label => [$start, $end])
                    <a @class(['chip-link', 'active' => $from->isSameDay($start) && $to->isSameDay($end)])
                       href="{{ $personUrl($start, $end) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($clipped)
            <div class="alert info">
                แสดงบนหน้าจอได้ครั้งละไม่เกิน {{ App\Http\Controllers\TechnicianWorkController::MAX_RANGE_DAYS }} วัน
                จึงตัดให้ถึง {{ thai_date($to, short: true) }} — ถ้าต้องการช่วงยาวกว่านี้ให้ใช้ปุ่มดาวน์โหลด Excel
            </div>
        @endif

        <div class="card" style="padding:0;overflow:hidden">
            <div class="month-bar">
                <div class="month-title">{{ $technician?->name }}</div>
                <div class="work-legend">
                    @foreach ($summary['types'] as $type => $count)
                        <span class="job-tag job-{{ $type }}">{{ $type }} <b>{{ baht($count) }}</b></span>
                    @endforeach
                </div>
            </div>

            @include('work._board', [
                'headLabel' => 'วันที่',
                'linkTitle' => 'ดูทุกช่างของวันนี้',
            ])
        </div>

        {{-- รายการเต็ม — อ่านหมายเหตุยาว ๆ ได้ครบ และไล่ดูทีละงานง่ายกว่าบนตารางเวลา --}}
        <div class="card" style="padding:0;overflow:hidden">
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th style="width:130px">วันที่</th>
                        <th style="width:120px">เวลา</th>
                        <th class="right" style="width:70px">ชม.</th>
                        <th style="width:90px">ประเภท</th>
                        <th style="width:140px">ป้ายทะเบียน</th>
                        <th>หมายเหตุ</th>
                        <th style="width:130px">ผู้บันทึก</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $job)
                        <tr class="clickable-row" onclick="openJob({{ $job->id }})">
                            <td><b>{{ thai_date($job->work_date, short: true) }}</b></td>
                            <td style="white-space:nowrap">{{ $job->startLabel() }}–{{ $job->endLabel() }} น.</td>
                            <td class="right">{{ $hours($job->durationMinutes()) }}</td>
                            <td><span class="job-tag job-{{ $job->job_type }}">{{ $job->job_type }}</span></td>
                            <td>{{ $job->license_plate ?? '-' }}</td>
                            <td class="sub job-note-cell">{{ $job->note ?? '-' }}</td>
                            <td class="sub">{{ $job->creator?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">ไม่มีงานในช่วงวันที่ที่เลือก</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @include('work._dialog')
@endsection
