@extends('layouts.app')

@section('title', 'รายการนัดหมาย')

@php
    // ค่าตั้งต้นคือ 'open' จึงไม่ต้องติดไปใน URL ลิงก์จะได้สั้นและเป็นค่ามาตรฐาน
    $keepStatus = $status === 'open' ? [] : ['status' => $status];
    $statusUrl = fn (string $key) => route('appointments.index', array_filter([
        'q' => $search,
        'status' => $key === 'open' ? null : $key,
    ]));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>รายการนัดหมาย</h2>
            <p class="muted">
                เรียงตามวันและเวลานัด ·
                @if ($search)
                    พบ {{ baht($appointments->total()) }} รายการจากคำค้น "{{ $search }}"
                @else
                    {{ App\Models\Appointment::statusLabel($status) }} {{ baht($appointments->total()) }} รายการ
                @endif
            </p>
        </div>
        <div class="page-tools">
            @include('partials.search-box', [
                'action' => route('appointments.index'),
                'hidden' => $keepStatus,
            ])
            <button type="button" class="primary" onclick="openAppointmentDialog()">+ เพิ่มข้อมูล</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง กรุณาตรวจสอบในฟอร์มอีกครั้ง</div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        {{-- งานที่ปิดแล้วถูกซ่อนไว้เป็นค่าตั้งต้น กดดูย้อนหลังได้จากแถบนี้ --}}
        <div class="status-bar">
            @foreach ($statusCounts as $key => $count)
                <a @class(['chip-link', 'active' => $key === $status]) href="{{ $statusUrl($key) }}">
                    {{ App\Models\Appointment::statusLabel($key) }}
                    <span class="chip-count">{{ baht($count) }}</span>
                </a>
            @endforeach
        </div>

        {{-- จอแคบไม่พอวางชิปทั้งแถบ สลับเป็น dropdown แทน (สลับด้วย CSS ทั้งคู่อยู่ในหน้าเดียว) --}}
        <form method="GET" action="{{ route('appointments.index') }}" class="status-jump">
            @if ($search)
                <input type="hidden" name="q" value="{{ $search }}">
            @endif

            <label class="status-jump-label" for="statusJump">สถานะ</label>

            <select name="status" id="statusJump" data-lock-on-submit>
                @foreach ($statusCounts as $key => $count)
                    <option value="{{ $key }}" @selected($key === $status)>
                        {{ App\Models\Appointment::statusLabel($key) }} · {{ baht($count) }}
                    </option>
                @endforeach
            </select>

            <noscript><button type="submit" class="ghost">กรอง</button></noscript>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:190px">วันที่และเวลานัด</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width:140px">เบอร์โทร</th>
                    <th style="width:185px">รถ</th>
                    <th class="right" style="width:100px">เลขไมล์</th>
                    <th style="width:120px">สถานะ</th>
                    <th style="width:120px">ผู้บันทึก</th>
                    <th class="right" style="width:120px">จัดการ</th>
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
                            @if ($appointment->source_missing_at)
                                <div class="sub source-missing"
                                     title="ใบขายนี้ถูกลบหรือถอนสถานะส่งมอบในระบบฝ่ายขายแล้ว">
                                    ⚠ ต้นทางในระบบขายไม่มีแล้ว
                                </div>
                            @endif
                        </td>
                        <td>
                            @include('partials.phone-cell', [
                                'phone' => $appointment->phone,
                                'phone2' => $appointment->phone2,
                            ])
                        </td>
                        <td>
                            @if ($appointment->model_name)
                                <b class="model-name">{{ $appointment->model_name }}</b>
                            @endif
                            <div class="sub"><code class="vin">{{ $appointment->vin }}</code></div>
                            @if ($appointment->delivered_at)
                                <div class="sub">ส่งมอบ {{ thai_date($appointment->delivered_at, short: true) }}</div>
                            @endif
                        </td>
                        <td class="right">
                            {{-- คิวที่ยังไม่เข้าศูนย์ เลขไมล์เป็น 0 แสดงขีดอ่านง่ายกว่าเลข 0 เรียงกันทั้งคอลัมน์ --}}
                            {{ $appointment->mileage > 0 ? baht($appointment->mileage) : '-' }}
                        </td>
                        <td>
                            <span class="badge status-{{ $appointment->statusKey() }}">{{ $appointment->status() }}</span>
                            @if ($appointment->isPostponed() && $appointment->recall_at)
                                <div class="sub recall-note"
                                     title="{{ $appointment->isUnreachable()
                                        ? 'ติดต่อไม่ได้ '.$appointment->unreachable_count.' ครั้ง รอโทรซ้ำอยู่'
                                        : 'ลูกค้าขอเลื่อนไว้เอง รอโทรซ้ำอยู่' }}">
                                    โทรซ้ำ {{ thai_date($appointment->recall_at, short: true) }}
                                </div>
                            @endif
                            {{-- ปิดคิวโดยไม่ได้เช็ค — ต้องเห็นเหตุผลย้อนหลังได้ว่าทำไมถึงไม่ได้ทำ --}}
                            @if ($appointment->isDeclined())
                                <div class="sub" title="{{ $appointment->declined_note }}">
                                    {{ $appointment->declineReasonLabel() }}
                                </div>
                            @endif
                            @if ($appointment->service_no)
                                <div class="sub">ครั้งที่ {{ $appointment->service_no }}</div>
                            @endif
                        </td>
                        <td class="sub">
                            {{ $appointment->creator?->name ?? ($appointment->isFromSales() ? 'ระบบดึงมา' : '-') }}
                        </td>
                        <td class="right">
                            <div class="row-actions">
                                {{-- ลูกค้าเปลี่ยนใจ หรือกดผิด — เอาคิวกลับเข้ารายการต้องโทรหาตามเดิม --}}
                                @if ($appointment->isDeclined())
                                    <form method="POST" action="{{ route('calls.decline', $appointment) }}"
                                          data-name="{{ $appointment->fullName() }}"
                                          onsubmit="return confirm('เอาคิวของ ' + this.dataset.name + ' กลับเข้ารายการต้องโทรหา ?')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="clear" value="1">
                                        <button type="submit" class="icon-btn act-restore" data-tip="เอาคิวกลับ"
                                                aria-label="เอาคิวกลับ">
                                            <svg class="ic" aria-hidden="true"><use href="#ic-restore"></use></svg>
                                        </button>
                                    </form>
                                @elseif ($appointment->completed_at === null)
                                    <button type="button" class="icon-btn act-complete" data-tip="ปิดงานเช็คระยะ"
                                            aria-label="ปิดงานเช็คระยะ" onclick="openComplete({{ $appointment->id }})">
                                        <svg class="ic" aria-hidden="true"><use href="#ic-complete"></use></svg>
                                    </button>
                                @endif

                                @if (auth()->user()->isSa())
                                    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}"
                                          data-name="{{ $appointment->fullName() }}"
                                          onsubmit="return confirm('ลบนัดหมายของ ' + this.dataset.name + ' ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn act-delete" data-tip="ลบนัดหมาย"
                                                aria-label="ลบนัดหมาย">
                                            <svg class="ic" aria-hidden="true"><use href="#ic-trash"></use></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            @if ($search)
                                ไม่พบนัดหมายที่ตรงกับ "{{ $search }}"
                                @if ($status !== 'all')
                                    ในสถานะ "{{ App\Models\Appointment::statusLabel($status) }}"
                                @endif
                            @elseif ($status !== 'open')
                                ไม่มีนัดหมายในสถานะ "{{ App\Models\Appointment::statusLabel($status) }}"
                            @else
                                ยังไม่มีนัดหมาย กด "เพิ่มข้อมูล" เพื่อเริ่มบันทึก
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $appointments])
    </div>

    @include('partials.action-icons')

    @include('partials.appointment-form')

    {{-- ปิดงานเช็คระยะ — กรอกเลขไมล์จริงตอนรถเข้าศูนย์ แล้วระบบสร้างคิวครั้งถัดไปให้เอง --}}
    <dialog id="completeDialog" class="modal">
        <form method="POST" id="completeForm">
            @csrf
            @method('PATCH')

            <div class="modal-head">
                <h2>ปิดงานเช็คระยะ</h2>
                <button type="button" class="modal-close" id="completeClose" aria-label="ปิด">&times;</button>
            </div>

            <div class="modal-body">
                <p class="muted" id="completeWho"></p>

                <label class="field">เลขไมล์ที่วัดได้ (กม.)
                    <input name="mileage" id="completeMileage" inputmode="numeric" placeholder="5200" required autofocus>
                    <span class="hint-inline">เลขไมล์จริงบนหน้าปัดตอนรถเข้าศูนย์</span>
                </label>

                <div class="alert info" style="margin-top:14px" id="completeNote"></div>
            </div>

            <div class="modal-foot">
                <button type="button" class="ghost" id="completeCancel">ยกเลิก</button>
                <button type="submit" class="primary" data-busy="กำลังปิดงาน…">ปิดงาน</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        @php
            // ปิดงานวันนี้แล้วคิวครั้งหน้าจะครบกำหนดวันไหน — คิดแบบเดียวกับ ServiceQueueImporter::createNextFor()
            $nextQueueNote = function ($a) {
                if (! $a->isFromSales() || $a->delivered_at === null) {
                    return 'นัดนี้คีย์เอง ไม่ได้อยู่ในตารางเช็คระยะ จึงไม่มีคิวถัดไป';
                }

                $next = App\Support\ServiceSchedule::nextAfterService(
                    $a->brand?->name ?? '', $a->model_id, now(), (int) $a->service_no
                );

                return $next
                    ? 'ปิดงานแล้วระบบจะสร้างคิวครั้งที่ '.$next['service_no'].' ให้อัตโนมัติ — ครบกำหนด '
                        .thai_date($next['due_date'], short: true).' (อีก '.$next['months'].' เดือนนับจากวันนี้)'
                    : 'ครบตารางเช็คระยะของรุ่นนี้แล้ว ปิดงานนี้แล้วไม่มีคิวถัดไป';
            };

            $completable = $appointments->filter(fn ($a) => $a->completed_at === null)
                ->mapWithKeys(fn ($a) => [$a->id => [
                    'name' => $a->fullName(),
                    'url' => route('appointments.complete', $a),
                    // ครั้งถัดไปนับจากวันที่ปิดงาน จึงบอกวันจริงไปเลยตั้งแต่ก่อนกด ไม่ต้องเดา
                    'next' => $nextQueueNote($a),
                ]]);
        @endphp
        const completable = @json($completable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        // เลือกสถานะบนมือถือแล้วกรองเลย ไม่ต้องกดปุ่มซ้ำ
        const statusJump = document.getElementById('statusJump');
        // requestSubmit() ยิง event submit ด้วย ตัวดักกดซ้ำกับแถบโหลดจึงทำงาน (submit() ไม่ยิง)
        if (statusJump) statusJump.addEventListener('change', () => {
            if (statusJump.form.requestSubmit) statusJump.form.requestSubmit();
            else statusJump.form.submit();
        });

        const completeDialog = document.getElementById('completeDialog');
        const completeForm = document.getElementById('completeForm');

        window.openComplete = function (id) {
            const item = completable[id];
            if (!item) return;

            completeForm.action = item.url;
            document.getElementById('completeWho').textContent = item.name;
            document.getElementById('completeNote').textContent = item.next;
            document.getElementById('completeMileage').value = '';

            completeDialog.showModal();
        };

        document.getElementById('completeClose').addEventListener('click', () => completeDialog.close());
        document.getElementById('completeCancel').addEventListener('click', () => completeDialog.close());
    </script>
@endpush
