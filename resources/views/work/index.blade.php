@extends('layouts.app')

@section('title', 'ปฏิทินงานช่าง')

@php
    $dayUrl = fn ($d) => route('work.index', ['date' => $d->toDateString()]);
    $hours = fn (int $minutes) => rtrim(rtrim(number_format($minutes / 60, 2), '0'), '.');
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

    <div class="card" style="padding:0;overflow:hidden">
        <div class="month-bar">
            <a class="cal-nav" title="วันก่อนหน้า" href="{{ $dayUrl($date->copy()->subDay()) }}">&lsaquo;</a>

            <form method="GET" action="{{ route('work.index') }}" class="month-jump">
                <input type="date" name="date" id="dayJump" value="{{ $date->toDateString() }}"
                       aria-label="เลือกวันที่" required>
                <noscript><button type="submit" class="ghost">ไป</button></noscript>
            </form>

            <a class="cal-nav" title="วันถัดไป" href="{{ $dayUrl($date->copy()->addDay()) }}">&rsaquo;</a>

            @unless ($date->isToday())
                <a class="chip-link" href="{{ route('work.index') }}">วันนี้</a>
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
