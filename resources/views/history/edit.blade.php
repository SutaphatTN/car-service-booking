@extends('layouts.app')

@section('title', 'แก้ไขประวัตินัดหมาย')

@php
    $a = $appointment;
    $fromSales = $a->isFromSales();
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $closeState = old('close_state', $a->completed_at ? 'done' : ($a->declined_at ? 'declined' : 'open'));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $a->fullName() }}</h2>
            <p class="muted">
                สถานะตอนนี้: <span class="badge status-{{ $a->statusKey() }}">{{ $a->status() }}</span>
                @if ($a->service_no) · เช็คระยะครั้งที่ {{ $a->service_no }} @endif
                · ผู้บันทึก {{ $a->creator?->name ?? ($fromSales ? 'ระบบดึงมา' : '-') }}
            </p>
        </div>
        <a href="{{ route('history.index') }}"><button type="button" class="ghost">← กลับประวัตินัดหมาย</button></a>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif
    @if ($errors->call->any())
        <div class="alert error">ผลการโทร: {{ $errors->call->first() }}</div>
    @endif

    <div class="card form-card" style="max-width:880px">
        <form method="POST" action="{{ route('history.update', $a) }}">
            @csrf
            @method('PATCH')

            <h3>ข้อมูลลูกค้าและรถ</h3>
            @if ($fromSales)
                <p class="hint-inline">คิวนี้ดึงมาจากฝ่ายขาย ชื่อ เบอร์ และข้อมูลรถถูกอัปเดตตามฝ่ายขายทุกรอบ จึงแก้ที่นี่ไม่ได้ ต้องแก้ที่ระบบขาย</p>
            @endif
            <fieldset class="plain" @disabled($fromSales)>
                <div class="grid cols-3" style="margin-top:10px">
                    <label class="field">คำนำหน้า
                        <select name="title_id">
                            <option value="">-</option>
                            @foreach ($titles as $title)
                                <option value="{{ $title->id }}" @selected((int) old('title_id', $a->title_id) === $title->id)>{{ $title->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">ชื่อ
                        <input name="first_name" value="{{ old('first_name', $a->first_name) }}" required>
                    </label>
                    <label class="field">นามสกุล
                        <input name="last_name" value="{{ old('last_name', $a->last_name) }}">
                    </label>
                </div>
                <div class="grid cols-2" style="margin-top:14px">
                    <label class="field">เบอร์โทร
                        <input name="phone" value="{{ old('phone', phone_format($a->phone)) }}" inputmode="tel">
                    </label>
                    <label class="field">เบอร์สำรอง
                        <input name="phone2" value="{{ old('phone2', phone_format($a->phone2)) }}" inputmode="tel">
                    </label>
                    <label class="field">เลขตัวถัง VIN
                        <input name="vin" value="{{ old('vin', $a->vin) }}">
                    </label>
                    <label class="field">รุ่น
                        <input name="model_name" value="{{ old('model_name', $a->model_name) }}">
                    </label>
                    <label class="field">รุ่นย่อย
                        <input name="submodel_name" value="{{ old('submodel_name', $a->submodel_name) }}">
                    </label>
                </div>
            </fieldset>

            <div class="grid cols-2" style="margin-top:14px">
                <label class="field">เลขไมล์ (กม.)
                    <input name="mileage" inputmode="numeric" value="{{ old('mileage', $a->mileage) }}" required>
                </label>
                <label class="field">หมายเหตุ
                    <input name="note" value="{{ old('note', $a->note) }}" maxlength="1000">
                </label>
            </div>

            <h3 style="margin-top:24px">นัดหมาย</h3>
            <div class="grid cols-2" style="margin-top:10px">
                <label class="field">วันนัด
                    <input type="date" name="appointment_date" value="{{ old('appointment_date', $a->appointment_at->toDateString()) }}" required>
                </label>
                <label class="field">เวลานัด
                    <input type="time" name="appointment_time" value="{{ old('appointment_time', $a->appointment_at->format('H:i')) }}" required>
                </label>
                <label class="field">ยืนยันนัดเมื่อ
                    <input type="datetime-local" name="confirmed_at" value="{{ old('confirmed_at', $dt($a->confirmed_at)) }}">
                    <span class="hint-inline">ว่าง = ยังไม่ได้ยืนยัน คิวจะกลับไปอยู่ในรายการต้องโทรหา</span>
                </label>
            </div>

            <h3 style="margin-top:24px">เลื่อนวันโทร</h3>
            <div class="grid cols-2" style="margin-top:10px">
                <label class="field">วันโทรซ้ำ
                    <input type="date" name="recall_at" value="{{ old('recall_at', $a->recall_at?->toDateString()) }}">
                    <span class="hint-inline">ว่าง = ไม่ได้เลื่อนวันโทร</span>
                </label>
                <label class="field">เหตุผลที่เลื่อน
                    <select name="recall_reason">
                        <option value="">-</option>
                        @foreach (App\Models\Appointment::RECALL_REASONS as $key => $label)
                            <option value="{{ $key }}" @selected(old('recall_reason', $a->recall_reason) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">โทรไม่ติดติดกัน (ครั้ง)
                    <input name="unreachable_count" inputmode="numeric" value="{{ old('unreachable_count', $a->unreachable_count) }}" required>
                </label>
            </div>

            <h3 style="margin-top:24px">ปิดงาน / ปิดคิว</h3>
            <div class="reason-list">
                @foreach ($closeStates as $key => $label)
                    <label class="reason-option">
                        <input type="radio" name="close_state" value="{{ $key }}" @checked($closeState === $key)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="grid cols-2 close-fields" data-for="done" style="margin-top:10px">
                <label class="field">วันที่รถเข้าเช็คจริง
                    <input type="date" name="serviced_at"
                           value="{{ old('serviced_at', ($a->serviced_at ?? $a->completed_at)?->toDateString()) }}">
                    <span class="hint-inline">คิวครั้งถัดไปนับจากวันนี้</span>
                </label>
                <label class="field">กดปิดงานเมื่อ
                    <input type="datetime-local" name="completed_at" value="{{ old('completed_at', $dt($a->completed_at) ?? now()->format('Y-m-d\TH:i')) }}">
                </label>
            </div>

            <div class="grid cols-2 close-fields" data-for="declined" style="margin-top:10px">
                <label class="field">ปิดคิวเมื่อ
                    <input type="datetime-local" name="declined_at" value="{{ old('declined_at', $dt($a->declined_at) ?? now()->format('Y-m-d\TH:i')) }}">
                </label>
                <label class="field">เหตุผลที่ไม่เข้าเช็ค
                    <select name="declined_reason">
                        <option value="">-</option>
                        @foreach (App\Models\Appointment::DECLINE_REASONS as $key => $label)
                            <option value="{{ $key }}" @selected(old('declined_reason', $a->declined_reason) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">รายละเอียดเพิ่มเติม
                    <input name="declined_note" value="{{ old('declined_note', $a->declined_note) }}" maxlength="200">
                </label>
            </div>

            @if ($nextQueue)
                <div class="alert info" style="margin-top:14px">
                    มีคิวครั้งที่ {{ $nextQueue->service_no }} แล้ว — ครบกำหนด {{ thai_date($nextQueue->appointment_at, short: true) }}
                    ({{ $nextQueue->status() }})
                    <a href="{{ route('history.edit', $nextQueue) }}">เปิดคิวนั้น</a>
                    @if ($nextQueue->needsCall())
                        <label class="reason-option" style="margin-top:6px">
                            <input type="checkbox" name="recalc_next" value="1" @checked(old('recalc_next'))>
                            <span>คิดวันครบกำหนดของคิวครั้งที่ {{ $nextQueue->service_no }} ใหม่จากวันที่รถเข้าที่แก้</span>
                        </label>
                    @endif
                </div>
            @elseif ($fromSales)
                <p class="hint-inline" style="margin-top:10px">ยังไม่มีคิวครั้งถัดไป — ถ้าเปลี่ยนเป็นเช็คแล้ว ระบบจะสร้างให้ตามตารางเช็คระยะ</p>
            @endif

            <div class="actions" style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end">
                <a href="{{ route('history.index') }}"><button type="button" class="ghost">ยกเลิก</button></a>
                <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึก</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;max-width:880px">
        <div style="padding:16px 18px 4px">
            <h3>ประวัติการโทร</h3>
            <p class="hint-inline">แก้ผลการโทรที่นี่ใช้กับรายงานการโทรเท่านั้น ไม่เปลี่ยนสถานะคิว (สถานะแก้ที่ฟอร์มด้านบน) · ติ๊ก "ยกเลิก" = กดผิด ไม่นับในรายงาน</p>
        </div>

        @forelse ($a->callLogs as $log)
            <form method="POST" action="{{ route('history.calls.update', [$a, $log]) }}"
                  @class(['call-log-row', 'is-cancelled' => $log->cancelled_at])
                  style="padding:14px 18px;border-top:1px solid var(--line)">
                @csrf
                @method('PATCH')

                <div class="sub" style="margin-bottom:8px">
                    บันทึกโดย {{ $log->user?->name ?? '-' }}
                    @if ($log->cancelled_at) · <b>ยกเลิกแล้ว</b> @endif
                </div>

                <div class="grid cols-2">
                    <label class="field">เวลาที่โทร
                        <input type="datetime-local" name="created_at" value="{{ $dt($log->created_at) }}" required>
                    </label>
                    <label class="field">ผลการโทร
                        <select name="result" required>
                            @foreach (App\Models\CallLog::RESULTS as $key => $label)
                                <option value="{{ $key }}" @selected($log->result === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">วันที่ต้องโทร (ตอนนั้น)
                        <input type="date" name="due_date" value="{{ $log->due_date?->toDateString() }}" required>
                    </label>
                    <label class="field">วันเวลานัด (ได้นัด)
                        <input type="datetime-local" name="booked_at" value="{{ $dt($log->booked_at) }}">
                    </label>
                    <label class="field">โทรซ้ำวันที่ (โทรไม่ติด/ยังไม่สะดวก)
                        <input type="date" name="recall_at" value="{{ $log->recall_at?->toDateString() }}">
                    </label>
                    <label class="field">เหตุผล (ไม่เข้าเช็ค)
                        <select name="declined_reason">
                            <option value="">-</option>
                            @foreach (App\Models\Appointment::DECLINE_REASONS as $key => $label)
                                <option value="{{ $key }}" @selected($log->declined_reason === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">หมายเหตุ
                        <input name="note" value="{{ $log->note }}" maxlength="200">
                    </label>
                </div>

                <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;margin-top:10px">
                    <label class="reason-option">
                        <input type="checkbox" name="cancelled" value="1" @checked($log->cancelled_at)>
                        <span>ยกเลิก (กดผิด)</span>
                    </label>
                    <button type="submit" class="ghost" data-busy="กำลังบันทึก…">บันทึกผลการโทรนี้</button>
                </div>
            </form>
        @empty
            <p class="empty" style="padding:16px 18px;border-top:1px solid var(--line)">ยังไม่มีประวัติการโทรของคิวนี้</p>
        @endforelse
    </div>
@endsection

@push('scripts')
    <script>
        // โชว์เฉพาะช่องของสถานะที่เลือก — ช่องที่ซ่อนถูกปิดไว้ด้วย จะได้ไม่ติด required
        (function () {
            const radios = document.querySelectorAll('input[name="close_state"]');
            const groups = document.querySelectorAll('.close-fields');

            function sync() {
                const state = document.querySelector('input[name="close_state"]:checked')?.value;
                groups.forEach((group) => {
                    const on = group.dataset.for === state;
                    group.hidden = !on;
                    group.querySelectorAll('input, select').forEach((el) => {
                        el.disabled = !on;
                        if (el.name !== 'declined_note') el.required = on;
                    });
                });
            }

            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endpush
