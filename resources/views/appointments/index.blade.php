@extends('layouts.app')

@section('title', 'รายการนัดหมาย')

@section('content')
    <div class="page-head">
        <div>
            <h2>รายการนัดหมาย</h2>
            <p class="muted">
                เรียงตามวันและเวลานัด ·
                @if ($search)
                    พบ {{ baht($appointments->total()) }} รายการจากคำค้น "{{ $search }}"
                @else
                    ทั้งหมด {{ baht($appointments->total()) }} รายการ
                @endif
            </p>
        </div>
        <div class="page-tools">
            @include('partials.search-box', ['action' => route('appointments.index')])
            <button type="button" class="primary" onclick="openAppointmentDialog()">+ เพิ่มข้อมูล</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error">กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง กรุณาตรวจสอบในฟอร์มอีกครั้ง</div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:190px">วันที่และเวลานัด</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width:140px">เบอร์โทร</th>
                    <th style="width:185px">รถ</th>
                    <th class="right" style="width:100px">เลขไมล์</th>
                    <th style="width:100px">สถานะ</th>
                    <th style="width:120px">ผู้บันทึก</th>
                    <th class="right" style="width:150px">จัดการ</th>
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
                            @if ($appointment->service_no)
                                <div class="sub">ครั้งที่ {{ $appointment->service_no }}</div>
                            @endif
                        </td>
                        <td class="sub">
                            {{ $appointment->creator?->name ?? ($appointment->isFromSales() ? 'ระบบดึงมา' : '-') }}
                        </td>
                        <td class="right">
                            <div class="row-actions">
                                @if ($appointment->completed_at === null)
                                    <button type="button" class="ghost"
                                            onclick="openComplete({{ $appointment->id }})">ปิดงาน</button>
                                @endif

                                @if (auth()->user()->isSa())
                                    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}"
                                          data-name="{{ $appointment->fullName() }}"
                                          onsubmit="return confirm('ลบนัดหมายของ ' + this.dataset.name + ' ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ghost danger">ลบ</button>
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
                <button type="submit" class="primary">ปิดงาน</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        @php
            $completable = $appointments->filter(fn ($a) => $a->completed_at === null)
                ->mapWithKeys(fn ($a) => [$a->id => [
                    'name' => $a->fullName(),
                    'url' => route('appointments.complete', $a),
                    'next' => $a->service_no
                        ? 'ปิดงานแล้วระบบจะสร้างคิวเช็คระยะครั้งถัดไปให้อัตโนมัติ (นับจากวันส่งมอบ)'
                        : 'นัดนี้คีย์เอง ไม่ได้อยู่ในตารางเช็คระยะ จึงไม่มีคิวถัดไป',
                ]]);
        @endphp
        const completable = @json($completable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

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
