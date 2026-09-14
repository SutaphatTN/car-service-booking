@extends('layouts.app')

@section('title', 'ต้องโทรหาลูกค้า')

@section('content')
    <div class="page-head">
        <div>
            <h2>ต้องโทรหาลูกค้า</h2>
            <p class="muted">
                คิวเช็คระยะที่ถึงกำหนดภายใน {{ $days }} วัน และยังไม่ได้ยืนยันวันเวลากับลูกค้า ·
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
                'hidden' => $days === App\Http\Controllers\CallListController::CALL_AHEAD_DAYS ? [] : ['days' => $days],
            ])
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:150px">ครบกำหนด</th>
                    <th>ลูกค้า</th>
                    <th style="width:150px">เบอร์โทร</th>
                    <th style="width:160px">รถ</th>
                    <th style="width:90px">ครั้งที่</th>
                    <th class="right" style="width:190px">จัดการ</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($calls as $call)
                    @php $overdue = $call->appointment_at->lt(now()->startOfDay()); @endphp
                    <tr @class(['row-overdue' => $overdue])>
                        <td>
                            <b>{{ thai_date($call->appointment_at, short: true) }}</b>
                            <div class="sub">
                                {{ $overdue ? 'เลยกำหนดแล้ว' : 'อีก '.(int) now()->startOfDay()->diffInDays($call->appointment_at).' วัน' }}
                            </div>
                        </td>
                        <td>
                            <b>{{ $call->fullName() }}</b>
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
                            <button type="button" class="primary"
                                    onclick="openConfirm({{ $call->id }})">ลงเวลานัด</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            @if ($search)
                                ไม่พบรายการที่ตรงกับ "{{ $search }}" ในช่วงนี้
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
                <button type="submit" class="primary">บันทึกนัด</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        @php
            $payload = $calls->mapWithKeys(fn ($c) => [$c->id => [
                'name' => $c->fullName(),
                'date' => $c->appointment_at->toDateString(),
                'time' => $c->appointment_at->format('H:i'),
                'url'  => route('calls.confirm', $c),
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
    </script>
@endpush
