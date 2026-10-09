@extends('layouts.app')

@section('title', 'รายงานนัดหมาย')

@php
    $quickRanges = [
        'เดือนนี้' => [now()->startOfMonth(), now()->endOfMonth()],
        'เดือนที่แล้ว' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        '7 วันข้างหน้า' => [now(), now()->addDays(6)],
        'ปีนี้' => [now()->startOfYear(), now()->endOfYear()],
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>รายงานนัดหมาย</h2>
            <p class="muted">เลือกช่วงวันที่เพื่อดูสรุปและรายการนัดหมาย</p>
        </div>
    </div>

    @include('reports.tabs')

    <div class="card">
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="filters">
                <label class="field">ตั้งแต่วันที่
                    <input type="date" name="from" value="{{ $from->toDateString() }}" required>
                </label>

                <label class="field">ถึงวันที่
                    <input type="date" name="to" value="{{ $to->toDateString() }}" required>
                </label>

                <button type="submit" class="primary">ดูรายงาน</button>
                {{-- ส่งออกตามช่วงที่แสดงอยู่ (ไม่ใช่ค่าที่เพิ่งพิมพ์ในช่องแต่ยังไม่กดดูรายงาน) --}}
                <a class="button ghost" download href="{{ route('reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">ดาวน์โหลด Excel</a>
            </div>
        </form>

        <div class="quick-ranges">
            <span class="muted">ช่วงที่ใช้บ่อย</span>
            @foreach ($quickRanges as $label => [$start, $end])
                <a class="chip-link @if ($from->isSameDay($start) && $to->isSameDay($end)) active @endif"
                   href="{{ route('reports.index', ['from' => $start->toDateString(), 'to' => $end->toDateString()]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <p class="range-note">
        รายงานช่วง <b>{{ thai_date($from) }}</b> ถึง <b>{{ thai_date($to) }}</b>
    </p>

    <div class="stats">
        <div class="stat">
            <div class="l">นัดหมายทั้งหมด</div>
            <div class="n">{{ baht($summary['total']) }}</div>
            <div class="s">รายการ</div>
        </div>
        <div class="stat">
            <div class="l">ช่วงที่เลือก</div>
            <div class="n">{{ baht($summary['span_days']) }}</div>
            <div class="s">วัน</div>
        </div>
        <div class="stat">
            <div class="l">วันที่มีนัด</div>
            <div class="n">{{ baht($summary['active_days']) }}</div>
            <div class="s">วัน</div>
        </div>
        <div class="stat">
            <div class="l">เฉลี่ยต่อวันที่มีนัด</div>
            <div class="n">{{ $summary['average_per_active_day'] }}</div>
            <div class="s">รายการ/วัน</div>
        </div>
        <div class="stat">
            <div class="l">วันที่นัดเยอะที่สุด</div>
            <div class="n small">{{ $summary['busiest_date'] ? thai_date($summary['busiest_date'], short: true) : '-' }}</div>
            <div class="s">{{ $summary['busiest_count'] ? baht($summary['busiest_count']).' รายการ' : 'ไม่มีข้อมูล' }}</div>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:190px">วันที่และเวลานัด</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width:140px">เบอร์โทร</th>
                    <th style="width:190px">รถ</th>
                    <th style="width:130px">ส่งมอบจริง</th>
                    <th class="right" style="width:120px">เลขไมล์ (กม.)</th>
                    <th style="width:140px">ผู้บันทึก</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>
                            <b>{{ $appointment->appointment_at->format('H:i') }} น.</b>
                            <div class="sub">{{ thai_date($appointment->appointment_at) }}</div>
                        </td>
                        <td>
                            <b>{{ $appointment->fullName() }}</b>
                            @if ($appointment->note)
                                <div class="sub appt-note" title="{{ $appointment->note }}">หมายเหตุ: {{ $appointment->note }}</div>
                            @endif
                        </td>
                        <td>
                            @include('partials.phone-cell', [
                                'phone' => $appointment->phone,
                                'phone2' => $appointment->phone2,
                            ])
                        </td>
                        <td>@include('partials.car-cell')</td>
                        <td class="sub">
                            {{ $appointment->delivered_at ? thai_date($appointment->delivered_at, short: true) : '-' }}
                        </td>
                        <td class="right">{{ baht($appointment->mileage) }}</td>
                        <td class="sub">{{ $appointment->creator?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">ไม่มีนัดหมายในช่วงวันที่ที่เลือก</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
