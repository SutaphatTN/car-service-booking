@extends('layouts.app')

@section('title', 'ปฏิทินนัดหมาย')

@php
    $today = now()->toDateString();
    $weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $dayLabels = collect($weeks)->flatten()->mapWithKeys(fn ($day) => [$day->toDateString() => thai_date($day)]);
    $monthTotal = collect($weeks)->flatten()
        ->filter(fn ($day) => $day->isSameMonth($month))
        ->sum(fn ($day) => count($events[$day->toDateString()] ?? []));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>ปฏิทินนัดหมาย</h2>
            <p class="muted">กดที่วันเพื่อดูว่ามีนัดอะไรบ้าง · เดือนนี้มี {{ $monthTotal }} นัด</p>
        </div>
        <button type="button" class="primary" onclick="openAppointmentDialog(selectedDate)">+ เพิ่มข้อมูล</button>
    </div>

    @if ($errors->any())
        <div class="alert error">กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง กรุณาตรวจสอบในฟอร์มอีกครั้ง</div>
    @endif

    <div class="calendar-layout">
        <div class="card calendar-card">
            <div class="cal-head">
                <a class="cal-nav" title="เดือนก่อนหน้า"
                   href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">&lsaquo;</a>
                <div class="cal-title">{{ thai_month($month) }}</div>
                <a class="cal-nav" title="เดือนถัดไป"
                   href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">&rsaquo;</a>
                <a class="cal-today" href="{{ route('calendar.index') }}">วันนี้</a>
            </div>

            <div class="cal-grid">
                @foreach ($weekdays as $index => $weekday)
                    <div @class(['cal-weekday', 'weekend' => $index === 0 || $index === 6])>{{ $weekday }}</div>
                @endforeach

                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        @php
                            $key = $day->toDateString();
                            $items = $byDate[$key] ?? collect();
                        @endphp
                        <button type="button" data-date="{{ $key }}"
                                @class([
                                    'cal-day',
                                    'outside' => ! $day->isSameMonth($month),
                                    'today' => $key === $today,
                                    'has-events' => $items->isNotEmpty(),
                                ])>
                            <span class="d">{{ $day->day }}</span>

                            @foreach ($items->take(3) as $item)
                                <span class="chip">{{ $item->appointment_at->format('H:i') }} {{ $item->first_name }}</span>
                            @endforeach

                            @if ($items->count() > 3)
                                <span class="more">+ อีก {{ $items->count() - 3 }} รายการ</span>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        <aside class="card day-panel">
            <h3 id="panelDate">-</h3>
            <p class="muted" id="panelCount"></p>
            <div id="panelList"></div>
            <button type="button" class="ghost" style="width:100%;margin-top:14px"
                    onclick="openAppointmentDialog(selectedDate)">+ เพิ่มนัดในวันนี้</button>
        </aside>
    </div>

    @include('partials.appointment-form', ['defaultDate' => $selectedDate])
@endsection

@push('scripts')
    <script>
        @php $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; @endphp
        const events = @json($events, $jsonFlags);
        const dayLabels = @json($dayLabels, $jsonFlags);
        let selectedDate = @json($selectedDate, $jsonFlags);

        const panelDate = document.getElementById('panelDate');
        const panelCount = document.getElementById('panelCount');
        const panelList = document.getElementById('panelList');

        function renderPanel(date) {
            const items = events[date] || [];

            panelDate.textContent = dayLabels[date] || date;
            panelCount.textContent = items.length ? items.length + ' นัด' : 'ไม่มีนัดหมายในวันนี้';
            panelList.replaceChildren();

            for (const item of items) {
                const row = document.createElement('div');
                row.className = 'day-item';

                const time = document.createElement('b');
                time.textContent = item.time + ' น.';

                const name = document.createElement('div');
                name.textContent = item.name;

                const detail = document.createElement('div');
                detail.className = 'sub';
                detail.textContent = item.phone
                    + (item.phone2 ? ' (สำรอง ' + item.phone2 + ')' : '')
                    + ' · ' + item.mileage + ' กม. · บันทึกโดย ' + item.creator;

                row.append(time, name, detail);

                // VIN กับวันส่งมอบเป็นช่องไม่บังคับ มีค่อยแสดงบรรทัดนี้
                if (item.vin || item.delivered) {
                    const car = document.createElement('div');
                    car.className = 'sub';
                    car.textContent = [
                        item.vin ? 'VIN ' + item.vin : '',
                        item.delivered ? 'ส่งมอบ ' + item.delivered : '',
                    ].filter(Boolean).join(' · ');

                    row.append(car);
                }
                panelList.append(row);
            }
        }

        function selectDay(date) {
            selectedDate = date;

            document.querySelectorAll('.cal-day').forEach((cell) => {
                cell.classList.toggle('selected', cell.dataset.date === date);
            });

            renderPanel(date);

            // เก็บวันที่ไว้ใน URL เผื่อกด refresh หรือส่งลิงก์ต่อ
            const url = new URL(window.location.href);
            url.searchParams.set('date', date);
            window.history.replaceState({}, '', url);
        }

        document.querySelectorAll('.cal-day').forEach((cell) => {
            cell.addEventListener('click', () => selectDay(cell.dataset.date));
        });

        selectDay(selectedDate);
    </script>
@endpush
