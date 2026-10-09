@extends('layouts.app')

@section('title', 'รายงานการโทร')

@php
    $quickRanges = [
        'วันนี้' => [now(), now()],
        'เมื่อวาน' => [now()->subDay(), now()->subDay()],
        '7 วันที่ผ่านมา' => [now()->subDays(6), now()],
        'เดือนนี้' => [now()->startOfMonth(), now()],
    ];

    $resultLabels = App\Http\Controllers\CallReportController::RESULT_LABELS;

    // ใช้สีป้ายชุดเดียวกับสถานะนัดหมาย จะได้อ่านความหมายเหมือนกันทั้งระบบ
    $badgeClass = [
        'confirmed' => 'status-booked',
        'unreachable' => 'status-unreachable',
        'not_ready' => 'status-not_ready',
        'declined' => 'status-declined',
        'pending' => 'status-waiting',
    ];

    $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $resultUrl = fn (string $key) => route('reports.calls', $range + ($key === 'all' ? [] : ['result' => $key]));
    $percent = fn (int $part, int $whole) => $whole ? round($part / $whole * 100) : 0;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>รายงานการโทร</h2>
            <p class="muted">
                คิวที่ต้องโทรในช่วงวันที่เลือก (ล่วงหน้า {{ $aheadDays }} วัน รวมคิวที่เลยกำหนด) และผลการโทรแต่ละสาย
            </p>
        </div>
    </div>

    @include('reports.tabs')

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <form method="GET" action="{{ route('reports.calls') }}">
            <div class="filters">
                <label class="field">ตั้งแต่วันที่
                    <input type="date" name="from" value="{{ $from->toDateString() }}" required>
                </label>

                <label class="field">ถึงวันที่
                    <input type="date" name="to" value="{{ $to->toDateString() }}" required>
                </label>

                <button type="submit" class="primary">ดูรายงาน</button>
                {{-- ส่งออกตามช่วงที่แสดงอยู่ (ไม่ใช่ค่าที่เพิ่งพิมพ์ในช่องแต่ยังไม่กดดูรายงาน) --}}
                <a class="button ghost" download href="{{ route('reports.calls.export', $range + ($result === 'all' ? [] : ['result' => $result])) }}">ดาวน์โหลด Excel</a>
            </div>
        </form>

        <div class="quick-ranges">
            <span class="muted">ช่วงที่ใช้บ่อย</span>
            @foreach ($quickRanges as $label => [$start, $end])
                <a @class(['chip-link', 'active' => $from->isSameDay($start) && $to->isSameDay($end)])
                   href="{{ route('reports.calls', ['from' => $start->toDateString(), 'to' => $end->toDateString()]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <p class="range-note">
        @if ($from->isSameDay($to))
            รายงานวันที่ <b>{{ thai_date($from) }}</b>
        @else
            รายงานช่วง <b>{{ thai_date($from) }}</b> ถึง <b>{{ thai_date($to) }}</b>
        @endif
    </p>

    <div class="stats">
        <div class="stat">
            <div class="l">ต้องโทร</div>
            <div class="n">{{ baht($counts['all']) }}</div>
            <div class="s">ราย</div>
        </div>
        <div class="stat">
            <div class="l">โทรแล้ว</div>
            <div class="n">{{ baht($counts['called']) }}</div>
            <div class="s">{{ $percent($counts['called'], $counts['all']) }}% ของที่ต้องโทร</div>
        </div>
        <div class="stat">
            <div class="l">ได้นัด</div>
            <div class="n">{{ baht($counts['confirmed']) }}</div>
            <div class="s">{{ $percent($counts['confirmed'], $counts['called']) }}% ของที่โทรแล้ว</div>
        </div>
        <div class="stat">
            <div class="l">เลื่อนโทร (ไม่ติด / ยังไม่สะดวก)</div>
            <div class="n">{{ baht($counts['unreachable'] + $counts['not_ready']) }}</div>
            <div class="s">ไม่ติด {{ baht($counts['unreachable']) }} · ยังไม่สะดวก {{ baht($counts['not_ready']) }}</div>
        </div>
        <div class="stat">
            <div class="l">ยังไม่ได้โทร</div>
            <div class="n">{{ baht($counts['pending']) }}</div>
            <div class="s">ราย</div>
        </div>
    </div>

    @if ($byUser->isNotEmpty())
        <div class="card" style="padding:0;overflow:hidden">
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>ผู้โทร</th>
                        <th class="right" style="width:110px">โทรทั้งหมด</th>
                        @foreach (App\Models\CallLog::RESULTS as $label)
                            <th class="right" style="width:130px">{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($byUser as $name => $userCounts)
                        <tr>
                            <td><b>{{ $name }}</b></td>
                            <td class="right"><b>{{ baht($userCounts['total']) }}</b></td>
                            @foreach (array_keys(App\Models\CallLog::RESULTS) as $key)
                                <td class="right">{{ baht($userCounts[$key] ?? 0) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="status-bar">
            @foreach ($counts as $key => $count)
                {{-- คั่นกลุ่ม: ภาพรวม | แยกตามผล --}}
                @if ($key === 'confirmed')
                    <span class="status-sep" aria-hidden="true"></span>
                @endif
                <a @class(['chip-link', 'st-'.$key, 'active' => $key === $result, 'is-zero' => $count === 0])
                   href="{{ $resultUrl($key) }}">
                    <span class="st-dot" aria-hidden="true"></span>
                    {{ $resultLabels[$key] }}
                    <span class="chip-count">{{ baht($count) }}</span>
                </a>
            @endforeach
        </div>

        {{-- จอแคบไม่พอวางชิปทั้งแถบ สลับเป็น dropdown แทน (สลับด้วย CSS) --}}
        <form method="GET" action="{{ route('reports.calls') }}" class="status-jump">
            <input type="hidden" name="from" value="{{ $from->toDateString() }}">
            <input type="hidden" name="to" value="{{ $to->toDateString() }}">

            <label class="status-jump-label" for="resultJump">ผลการโทร</label>

            <select name="result" id="resultJump" data-lock-on-submit>
                @foreach ($counts as $key => $count)
                    <option value="{{ $key }}" @selected($key === $result)>
                        {{ $resultLabels[$key] }} · {{ baht($count) }}
                    </option>
                @endforeach
            </select>

            <noscript><button type="submit" class="ghost">กรอง</button></noscript>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:120px">ครบกำหนด</th>
                    <th style="width:130px">ต้องโทร</th>
                    <th>ลูกค้า</th>
                    <th style="width:150px">เบอร์โทร</th>
                    <th style="width:170px">รถ</th>
                    <th style="width:230px">ผลการโทร</th>
                    <th style="width:160px">บันทึกโดย</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        $call = $row['appointment'];
                        $last = $row['last'];
                    @endphp
                    <tr>
                        {{-- วันครบกำหนดตามตารางเช็คระยะ / วันที่ควรเริ่มโทร (แบบเดียวกับหน้า "ต้องโทรหา") --}}
                        <td><b>{{ thai_date($row['service_due'], short: true) }}</b></td>
                        <td>
                            <b>{{ thai_date($row['call_from'], short: true) }}</b>
                            @if ($row['recalled'])
                                <div class="sub recall-note">นัดโทรซ้ำ</div>
                            @elseif ($row['call_from']->lt($from))
                                <div class="sub">ค้างจากก่อนหน้า</div>
                            @endif
                        </td>
                        <td>
                            <b>{{ $call->fullName() }}</b>
                            @if ($call->service_no)
                                <div class="sub">เช็คระยะครั้งที่ {{ $call->service_no }}</div>
                            @endif
                        </td>
                        <td>
                            @include('partials.phone-cell', ['phone' => $call->phone, 'phone2' => $call->phone2])
                        </td>
                        <td>
                            @include('partials.car-cell', ['appointment' => $call])
                        </td>
                        <td>
                            <span class="badge {{ $badgeClass[$row['result']] }}">{{ $resultLabels[$row['result']] }}</span>
                            @if ($last)
                                <div class="sub">{{ $last->detail() }}</div>
                                {{-- โทรหลายรอบในช่วงเดียวกัน — แสดงผลล่าสุด พร้อมบอกว่ามีรอบก่อนหน้า --}}
                                @if ($row['logs']->count() > 1)
                                    <div class="sub">โทร {{ $row['logs']->count() }} ครั้ง: {{ $row['logs']->map(fn ($log) => $log->resultLabel())->implode(' → ') }}</div>
                                @endif
                            @endif
                        </td>
                        <td class="sub">
                            @if ($last)
                                {{ $last->user?->name ?? '-' }}
                                <div>{{ thai_date($last->created_at, short: true) }} {{ $last->created_at->format('H:i') }} น.</div>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            @if ($result === 'all')
                                ไม่มีคิวที่ต้องโทรในช่วงวันที่ที่เลือก
                            @else
                                ไม่มีรายการ "{{ $resultLabels[$result] }}" ในช่วงวันที่ที่เลือก
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const resultJump = document.getElementById('resultJump');

        if (resultJump) resultJump.addEventListener('change', () => {
            if (resultJump.form.requestSubmit) resultJump.form.requestSubmit();
            else resultJump.form.submit();
        });
    </script>
@endsection
