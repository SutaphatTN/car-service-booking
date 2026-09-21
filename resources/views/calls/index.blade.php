@extends('layouts.app')

@section('title', 'ต้องโทรหาลูกค้า')

@php
    $defaultDays = App\Http\Controllers\CallListController::CALL_AHEAD_DAYS;

    // ลิงก์ทุกเส้นในหน้านี้ต้องพาคำค้นติดไปด้วย ไม่งั้นกดเปลี่ยนเดือนแล้วคำค้นหลุด
    $keepSearch = $search ? ['q' => $search] : [];
    $keepDays = $days === $defaultDays ? [] : ['days' => $days];

    $monthUrl = fn ($m) => route('calls.index', $keepSearch + ['month' => $m->format('Y-m')]);
    $daysUrl = route('calls.index', $keepSearch + $keepDays);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>ต้องโทรหาลูกค้า</h2>
            <p class="muted">
                @if ($month)
                    คิวเช็คระยะของเดือน{{ thai_month($month) }} ที่ยังไม่ได้ยืนยันวันเวลากับลูกค้า ·
                @else
                    คิวเช็คระยะที่ถึงกำหนดภายใน {{ $days }} วัน และยังไม่ได้ยืนยันวันเวลากับลูกค้า ·
                @endif
                @if ($search)
                    พบ {{ baht($calls->total()) }} รายจากคำค้น "{{ $search }}"
                @else
                    ทั้งหมด {{ baht($calls->total()) }} ราย
                @endif
                @if ($overdueCount)
                    · <b class="overdue-note">เลยกำหนดแล้ว {{ baht($overdueCount) }} ราย</b>
                @endif
            </p>
        </div>
        <div class="page-tools">
            @include('partials.search-box', [
                'action' => route('calls.index'),
                'hidden' => $month ? ['month' => $month->format('Y-m')] : $keepDays,
            ])
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="month-bar">
            @if ($month)
                @php
                    $thisMonth = now()->format('Y-m');
                    $selected = $month->format('Y-m');

                    // เดือนที่กำลังดูกับเดือนปัจจุบันต้องมีในรายการเสมอ ถึงจะไม่มีคิวสักใบ
                    $monthKeys = $monthCounts->keys()
                        ->push($thisMonth)->push($selected)
                        ->unique()->sort()->values();
                @endphp

                <a class="cal-nav" title="เดือนก่อนหน้า"
                   href="{{ $monthUrl($month->copy()->subMonth()) }}">&lsaquo;</a>

                {{-- กระโดดข้ามไปเดือนไหนก็ได้ในคลิกเดียว ไม่ต้องกดทีละเดือนแล้วรอโหลด --}}
                <form method="GET" action="{{ route('calls.index') }}" class="month-jump">
                    @if ($search)
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif

                    <select name="month" id="monthJump" data-lock-on-submit
                            aria-label="เลือกเดือนที่ต้องการดู">
                        @foreach ([
                            'เดือนที่ผ่านมา (คิวค้าง)' => $monthKeys->filter(fn ($ym) => $ym < $thisMonth),
                            'เดือนนี้และถัดไป' => $monthKeys->filter(fn ($ym) => $ym >= $thisMonth),
                        ] as $groupLabel => $keys)
                            @continue($keys->isEmpty())
                            <optgroup label="{{ $groupLabel }}">
                                @foreach ($keys as $ym)
                                    <option value="{{ $ym }}" @selected($ym === $selected)>
                                        {{ thai_month($ym.'-01') }} · {{ baht($monthCounts[$ym] ?? 0) }} ราย
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>

                    {{-- เปลี่ยนแล้วไปเองด้วย JS ปุ่มนี้ไว้เผื่อเบราว์เซอร์ปิด JS --}}
                    <noscript><button type="submit" class="ghost">ไป</button></noscript>
                </form>

                <a class="cal-nav" title="เดือนถัดไป"
                   href="{{ $monthUrl($month->copy()->addMonth()) }}">&rsaquo;</a>

                @unless ($month->isSameMonth(now()))
                    <a class="chip-link" href="{{ $monthUrl(now()) }}">เดือนนี้</a>
                @endunless

                <a class="chip-link month-switch" href="{{ $daysUrl }}">ดูแบบ {{ $days }} วันข้างหน้า</a>
            @else
                <div class="month-title">{{ $days }} วันข้างหน้า</div>
                <a class="chip-link month-switch" href="{{ $monthUrl(now()) }}">ดูเป็นรายเดือน</a>
            @endif
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:160px">ต้องโทรวันที่</th>
                    <th>ลูกค้า</th>
                    <th style="width:150px">เบอร์โทร</th>
                    <th style="width:160px">รถ</th>
                    <th style="width:90px">ครั้งที่</th>
                    <th class="right" style="width:250px">จัดการ</th>
                </tr>
                </thead>
                <tbody>
                {{-- มุมมองรายเดือนมีสองกลุ่มปนกัน (คิวค้างเก่า + คิวของเดือนนี้) จึงคั่นหัวกลุ่มให้ --}}
                @php $lastGroup = null; @endphp
                @forelse ($calls as $call)
                    @php
                        $overdue = $call->isCallOverdue();
                        $daysLeft = (int) now()->startOfDay()->diffInDays($call->callDueDate());
                    @endphp

                    @if ($month && $overdue !== $lastGroup)
                        @php $lastGroup = $overdue; @endphp
                        <tr class="group-row">
                            <td colspan="6" @class(['group-overdue' => $overdue])>
                                @if ($overdue)
                                    เลยกำหนดแล้ว — ค้างมาจากก่อนหน้านี้ ควรโทรก่อน
                                @else
                                    ครบกำหนดในเดือน{{ thai_month($month) }}
                                @endif
                            </td>
                        </tr>
                    @endif

                    <tr @class(['row-overdue' => $overdue])>
                        <td>
                            <b>{{ thai_date($call->callDueDate(), short: true) }}</b>
                            <div class="sub">
                                @if ($overdue)
                                    เลยกำหนดแล้ว
                                @elseif ($daysLeft === 0)
                                    วันนี้
                                @else
                                    อีก {{ $daysLeft }} วัน
                                @endif
                            </div>
                            @if ($call->isUnreachable())
                                <div class="sub recall-note">
                                    เลื่อนจาก {{ thai_date($call->appointment_at, short: true) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <b>{{ $call->fullName() }}</b>
                            @if ($call->isUnreachable())
                                <div class="sub">
                                    <span class="badge status-unreachable">
                                        ติดต่อไม่ได้ {{ $call->unreachable_count }} ครั้ง
                                    </span>
                                </div>
                            @endif
                            @if ($call->delivered_at)
                                <div class="sub">ส่งมอบ {{ thai_date($call->delivered_at, short: true) }}</div>
                            @endif
                        </td>
                        <td>
                            @include('partials.phone-cell', ['phone' => $call->phone, 'phone2' => $call->phone2])
                        </td>
                        <td>
                            {{ $call->model_name ?? '-' }}
                            <div class="sub"><code class="vin">{{ $call->vin }}</code></div>
                        </td>
                        <td>{{ $call->service_no ? 'ครั้งที่ '.$call->service_no : '-' }}</td>
                        <td class="right">
                            <div class="row-actions">
                                <button type="button" class="ghost"
                                        onclick="openUnreachable({{ $call->id }})">{{ $call->isUnreachable() ? 'เลื่อนอีก' : 'ติดต่อไม่ได้' }}</button>
                                <button type="button" class="primary"
                                        onclick="openConfirm({{ $call->id }})">ลงเวลานัด</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            @if ($search)
                                ไม่พบรายการที่ตรงกับ "{{ $search }}" ใน{{ $month ? 'เดือน'.thai_month($month) : 'ช่วงนี้' }}
                            @elseif ($month)
                                ไม่มีคิวที่ต้องโทรในเดือน{{ thai_month($month) }}
                            @else
                                ไม่มีรายการที่ต้องโทรในช่วงนี้
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $calls])
    </div>

    {{-- ฟอร์มลงเวลานัดหลังโทรคุยกับลูกค้า --}}
    <dialog id="confirmDialog" class="modal">
        <form method="POST" id="confirmForm">
            @csrf
            @method('PATCH')

            <div class="modal-head">
                <h2>ลงเวลานัด</h2>
                <button type="button" class="modal-close" id="confirmClose" aria-label="ปิด">&times;</button>
            </div>

            <div class="modal-body">
                <p class="muted" id="confirmWho"></p>

                <div class="grid cols-2">
                    <label class="field">วันที่นัด
                        <input type="date" name="appointment_date" id="confirmDate" required>
                    </label>

                    <label class="field">เวลานัด
                        <select name="appointment_time" id="confirmTime" required>
                            @foreach ($timeSlots as $group => $slots)
                                <optgroup label="{{ $group }}">
                                    @foreach ($slots as $slot)
                                        <option value="{{ $slot }}">{{ $slot }} น.</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="alert info" style="margin-top:14px">
                    วันที่ตั้งไว้คือวันครบกำหนดตามตารางเช็คระยะ ถ้าลูกค้าไม่สะดวกให้เปลี่ยนได้ตามที่ตกลงกัน
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="ghost" id="confirmCancel">ยกเลิก</button>
                <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึกนัด</button>
            </div>
        </form>
    </dialog>

    {{-- โทรแล้วไม่ติด — เลื่อนไปโทรซ้ำอีกวัน คิวจะไปโผล่ในรายการนี้ตามวันที่เลือก --}}
    <dialog id="unreachableDialog" class="modal">
        <form method="POST" id="unreachableForm">
            @csrf
            @method('PATCH')

            <div class="modal-head">
                <h2>ติดต่อไม่ได้</h2>
                <button type="button" class="modal-close" id="unreachableClose" aria-label="ปิด">&times;</button>
            </div>

            <div class="modal-body">
                <p class="muted" id="unreachableWho"></p>

                <label class="field">จะโทรซ้ำวันที่
                    <input type="date" name="recall_date" id="recallDate"
                           min="{{ now()->toDateString() }}" required>
                    <span class="hint-inline">
                        วันครบกำหนดเช็คระยะไม่ขยับ — คิวนี้จะเลื่อนไปโผล่ในรายการต้องโทรหาตามวันที่เลือกแทน
                    </span>
                </label>

                <div class="alert info" style="margin-top:14px" id="unreachableNote"></div>
            </div>

            <div class="modal-foot">
                {{-- กดผิด หรือติดต่อได้แล้วแต่ยังไม่นัด — เอาสถานะออกให้กลับไปเป็นคิวรอโทรตามปกติ --}}
                <button type="submit" name="clear" value="1" formnovalidate
                        class="ghost danger modal-foot-start" id="unreachableClear"
                        data-busy="กำลังล้าง…">ล้างสถานะนี้</button>
                <button type="button" class="ghost" id="unreachableCancel">ยกเลิก</button>
                <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึก</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        @php
            $today = now()->startOfDay();

            $payload = $calls->mapWithKeys(fn ($c) => [$c->id => [
                'name' => $c->fullName(),
                'date' => $c->appointment_at->toDateString(),
                'time' => $c->appointment_at->format('H:i'),
                'url'  => route('calls.confirm', $c),

                'unreachableUrl' => route('calls.unreachable', $c),
                'unreachable' => $c->isUnreachable(),
                'tries' => $c->unreachable_count,
                // วันเดิมที่เลื่อนไว้ถ้ายังไม่ถึง ไม่งั้นตั้งต้นอีก 3 วัน (วันที่ย้อนหลังบันทึกไม่ได้)
                'recallDate' => $c->recall_at && $c->recall_at->gte($today)
                    ? $c->recall_at->toDateString()
                    : $today->copy()->addDays(3)->toDateString(),
            ]]);
        @endphp
        const calls = @json($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        const confirmDialog = document.getElementById('confirmDialog');
        const confirmForm = document.getElementById('confirmForm');
        const confirmDate = document.getElementById('confirmDate');
        const confirmTime = document.getElementById('confirmTime');
        const confirmWho = document.getElementById('confirmWho');

        window.openConfirm = function (id) {
            const call = calls[id];
            if (!call) return;

            confirmForm.action = call.url;
            confirmDate.value = call.date;
            confirmWho.textContent = call.name;

            // เวลาที่ตั้งไว้อาจไม่อยู่ในช่วงเวลาทำการ (เช่น 08:00 ตั้งต้น) เลือกได้ก็เลือก
            confirmTime.value = call.time;
            if (!confirmTime.value) confirmTime.selectedIndex = 0;

            confirmDialog.showModal();
        };

        document.getElementById('confirmClose').addEventListener('click', () => confirmDialog.close());
        document.getElementById('confirmCancel').addEventListener('click', () => confirmDialog.close());

        // ---- เลือกเดือนแล้วไปเลย ไม่ต้องกดปุ่มซ้ำ ----
        const monthJump = document.getElementById('monthJump');
        // requestSubmit() ยิง event submit ด้วย ตัวดักกดซ้ำกับแถบโหลดจึงทำงาน (submit() ไม่ยิง)
        if (monthJump) monthJump.addEventListener('change', () => {
            if (monthJump.form.requestSubmit) monthJump.form.requestSubmit();
            else monthJump.form.submit();
        });

        // ---- ติดต่อไม่ได้ ----
        const unreachableDialog = document.getElementById('unreachableDialog');
        const unreachableForm = document.getElementById('unreachableForm');
        const recallDate = document.getElementById('recallDate');
        const unreachableClear = document.getElementById('unreachableClear');

        window.openUnreachable = function (id) {
            const call = calls[id];
            if (!call) return;

            unreachableForm.action = call.unreachableUrl;
            recallDate.value = call.recallDate;
            document.getElementById('unreachableWho').textContent = call.name;
            document.getElementById('unreachableNote').textContent = call.unreachable
                ? 'โทรไม่ติดมาแล้ว ' + call.tries + ' ครั้ง — เลือกวันใหม่เพื่อเลื่อนต่อ'
                : 'คิวจะยังอยู่ในรายการต้องโทรหา แต่ขยับไปอยู่วันที่เลือก';

            // ล้างสถานะได้เฉพาะคิวที่ติดธงไว้แล้ว
            unreachableClear.hidden = !call.unreachable;

            unreachableDialog.showModal();
        };

        document.getElementById('unreachableClose').addEventListener('click', () => unreachableDialog.close());
        document.getElementById('unreachableCancel').addEventListener('click', () => unreachableDialog.close());
    </script>
@endpush
