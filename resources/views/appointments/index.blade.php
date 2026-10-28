@extends('layouts.app')

@section('title', 'รายการนัดหมาย')

@php
    // ค่าตั้งต้นคือ 'open' จึงไม่ต้องติดไปใน URL ลิงก์จะได้สั้นและเป็นค่ามาตรฐาน
    $keepStatus = $status === 'open' ? [] : ['status' => $status];
    // ปุ่มแก้ไข — ผู้ใช้ใน APPOINTMENT_EDITOR_IDS แก้ได้ทุกนัด, sa เลื่อนวันเวลาได้เฉพาะนัดที่ยืนยันแล้ว
    $canEdit = auth()->user()->canEditAppointments() || auth()->user()->isSa();
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

    @if ($errors->any() || $errors->edit->any())
        <div class="alert error">
            {{-- วันที่รถเข้ามาจากป็อปอัปปิดงาน ซึ่งปิดไปแล้วตอนโหลดหน้าใหม่ จึงต้องบอกเหตุผลตรง ๆ ตรงนี้ --}}
            {{ $errors->edit->first('unconfirm') ?: $errors->first('serviced_date') ?: 'กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง กรุณาตรวจสอบในฟอร์มอีกครั้ง' }}
        </div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        {{-- งานที่ปิดแล้วถูกซ่อนไว้เป็นค่าตั้งต้น กดดูย้อนหลังได้จากแถบนี้ --}}
        <div class="status-bar">
            @foreach ($statusCounts as $key => $count)
                {{-- คั่นกลุ่ม: งานที่ยังเปิดอยู่ | งานที่ปิดแล้ว | ทั้งหมด --}}
                @if (in_array($key, ['declined', 'all'], true))
                    <span class="status-sep" aria-hidden="true"></span>
                @endif
                <a @class(['chip-link', 'st-'.$key, 'active' => $key === $status, 'is-zero' => $count === 0])
                   href="{{ $statusUrl($key) }}">
                    <span class="st-dot" aria-hidden="true"></span>
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
                            @if ($appointment->note)
                                <div class="sub appt-note" title="{{ $appointment->note }}">หมายเหตุ: {{ $appointment->note }}</div>
                            @endif
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
                            @include('partials.car-cell')
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
                                @elseif ($appointment->canComplete())
                                    <button type="button" class="icon-btn act-complete" data-tip="ปิดงานเช็คระยะ"
                                            aria-label="ปิดงานเช็คระยะ" onclick="openComplete({{ $appointment->id }})">
                                        <svg class="ic" aria-hidden="true"><use href="#ic-complete"></use></svg>
                                    </button>
                                @elseif ($appointment->completed_at === null)
                                    {{-- ยังไม่ถึงวันนัด — โชว์ปุ่มจาง ๆ ไว้ให้รู้ว่ามี แต่กดไม่ได้จนกว่าจะถึงวัน --}}
                                    <button type="button" class="icon-btn act-complete" disabled
                                            data-tip="ปิดงานได้ตั้งแต่ {{ thai_date($appointment->appointment_at, short: true) }}"
                                            aria-label="ยังไม่ถึงวันนัด ปิดงานไม่ได้">
                                        <svg class="ic" aria-hidden="true"><use href="#ic-complete"></use></svg>
                                    </button>
                                @endif

                                @if (auth()->user()->canEditAppointment($appointment))
                                    <button type="button" class="icon-btn act-edit" data-tip="แก้ไขนัดหมาย"
                                            aria-label="แก้ไขนัดหมาย" onclick="openEdit({{ $appointment->id }})">
                                        <svg class="ic" aria-hidden="true"><use href="#ic-edit"></use></svg>
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

                {{-- กดปิดงานช้ากว่าวันที่รถเข้าจริงได้ — ให้เลือกวันที่เข้าจริง คิวครั้งหน้านับจากวันนี้ --}}
                <label class="field" style="margin-top:12px">วันที่รถเข้าเช็คจริง
                    <input type="date" name="serviced_date" id="completeDate" required>
                    <span class="hint-inline">ปิดงานย้อนหลังได้ เลือกได้ตั้งแต่วันนัดจนถึงวันนี้</span>
                </label>

                <div class="alert info" style="margin-top:14px" id="completeNote"></div>
            </div>

            <div class="modal-foot">
                <button type="button" class="ghost" id="completeCancel">ยกเลิก</button>
                <button type="submit" class="primary" data-busy="กำลังปิดงาน…">ปิดงาน</button>
            </div>
        </form>
    </dialog>

    @if ($canEdit)
        {{--
            แก้ไขนัดหมาย — ใช้ตอนกดผิด เช่นตั้งใจเลื่อนโทรแต่ไปกดลงเวลานัด
            คิวจากฝ่ายขายแก้ได้แค่วันเวลา เลขไมล์ หมายเหตุ (ข้อมูลลูกค้า/รถถูกทับจากระบบขายทุกรอบ)
        --}}
        <dialog id="editDialog" class="modal">
            <form method="POST" id="editForm">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_edit_id" id="editId" value="{{ old('_edit_id') }}">

                <div class="modal-head">
                    <h2>แก้ไขนัดหมาย</h2>
                    <button type="button" class="modal-close" onclick="editDialog.close()" aria-label="ปิด">&times;</button>
                </div>

                <div class="modal-body">
                    <fieldset id="editCustomer" class="plain">
                        <div class="grid cols-3">
                            <label class="field">คำนำหน้า
                                <select name="title_id" id="editTitle" required>
                                    <option value="">-- เลือก --</option>
                                    @foreach ($titles as $title)
                                        <option value="{{ $title->id }}">{{ $title->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">ชื่อ
                                <input name="first_name" id="editFirst" maxlength="100" required>
                            </label>
                            <label class="field">นามสกุล
                                <input name="last_name" id="editLast" maxlength="100" required>
                            </label>
                        </div>

                        <div class="grid cols-2" style="margin-top:12px">
                            <label class="field">เบอร์โทร
                                <input name="phone" id="editPhone" inputmode="numeric" maxlength="12" required>
                            </label>
                            <label class="field">เบอร์สำรอง <span class="muted">(ไม่บังคับ)</span>
                                <input name="phone2" id="editPhone2" inputmode="numeric" maxlength="12">
                            </label>
                        </div>

                        <div class="grid cols-2" style="margin-top:12px">
                            <label class="field">เลขตัวถัง VIN
                                <input name="vin" id="editVin" maxlength="17" class="vin-input" spellcheck="false" required>
                            </label>
                            <label class="field">วันที่ส่งมอบจริง <span class="muted">(ไม่บังคับ)</span>
                                <input type="date" name="delivered_at" id="editDelivered">
                            </label>
                        </div>

                        <div class="grid cols-2" style="margin-top:12px">
                            <label class="field">รุ่นหลัก
                                <select name="model_id" id="editModel">
                                    <option value="">-- เลือก --</option>
                                </select>
                                <span class="hint-inline" id="editVinStatus"></span>
                            </label>
                            <label class="field">รุ่นย่อย <span class="muted">(ไม่บังคับ)</span>
                                <select name="submodel_name" id="editSubmodel">
                                    <option value="">-- เลือก --</option>
                                </select>
                            </label>
                        </div>
                    </fieldset>

                    <p class="alert info" id="editFromSales" style="margin:0" hidden>
                        คิวนี้ดึงมาจากระบบขาย — ชื่อ เบอร์ และข้อมูลรถแก้ที่ระบบขาย ตรงนี้แก้ได้แค่วันเวลานัด เลขไมล์ และหมายเหตุ
                    </p>

                    <div class="grid cols-3" style="margin-top:12px">
                        <label class="field">วันที่นัด
                            <input type="date" name="appointment_date" id="editDate" required>
                        </label>
                        <label class="field">เวลานัด
                            <select name="appointment_time" id="editTime" required>
                                @foreach ($timeSlots as $group => $slots)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($slots as $slot)
                                            <option value="{{ $slot }}">{{ $slot }} น.</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">เลขไมล์ (กม.)
                            <input name="mileage" id="editMileage" inputmode="numeric" required>
                        </label>
                    </div>

                    <label class="field" style="display:block;margin-top:12px">หมายเหตุ <span class="muted">(ไม่บังคับ)</span>
                        <textarea name="note" id="editNote" rows="3" maxlength="1000"></textarea>
                    </label>

                    @if ($errors->edit->any() && ! $errors->edit->has('unconfirm'))
                        <p class="err" style="margin:12px 0 0">{{ $errors->edit->first() }}</p>
                    @endif
                </div>

                <div class="modal-foot">
                    {{-- กดลงเวลานัดผิด — เอาคิวกลับไปรอโทร แล้วไปกดเลื่อนโทรใหม่ที่หน้าต้องโทรหา --}}
                    <button type="button" class="ghost danger modal-foot-start" id="editUnconfirm" hidden>
                        ยกเลิกการยืนยันนัด
                    </button>
                    <button type="button" class="ghost" onclick="editDialog.close()">ยกเลิก</button>
                    <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึก</button>
                </div>
            </form>
        </dialog>

        <form method="POST" id="unconfirmForm" hidden>
            @csrf
            @method('PATCH')
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        @php
            // คิวครั้งหน้าห่างจากวันที่รถเข้ากี่เดือน — null = ไม่มีคิวถัดไป
            // วันครบกำหนดจริงคิดใน JS ตามวันที่เลือก แบบเดียวกับ ServiceQueueImporter::createNextFor()
            $nextQueue = function ($a) {
                if (! $a->isFromSales() || $a->delivered_at === null) {
                    return ['months' => null, 'none' => 'นัดนี้คีย์เอง ไม่ได้อยู่ในตารางเช็คระยะ จึงไม่มีคิวถัดไป'];
                }

                $months = App\Support\ServiceSchedule::intervalAfter($a->brand?->name ?? '', $a->model_id, (int) $a->service_no);

                return $months === null
                    ? ['months' => null, 'none' => 'ครบตารางเช็คระยะของรุ่นนี้แล้ว ปิดงานนี้แล้วไม่มีคิวถัดไป']
                    : ['months' => $months, 'serviceNo' => (int) $a->service_no + 1];
            };

            $completable = $appointments->filter(fn ($a) => $a->canComplete())
                ->mapWithKeys(fn ($a) => [$a->id => [
                    'name' => $a->fullName(),
                    'url' => route('appointments.complete', $a),
                    'minDate' => $a->appointment_at->toDateString(),
                    'next' => $nextQueue($a),
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

        const completeDate = document.getElementById('completeDate');
        const completeNote = document.getElementById('completeNote');
        const thaiMonthsShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        let completing = null;

        // วันที่ตามเวลาเครื่อง ไม่ใช่ UTC — toISOString() เช้ามืดจะได้วันของเมื่อวาน
        const isoDate = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

        // เหมือน Carbon::addMonthsNoOverflow() — 31 ส.ค. + 6 เดือน = 28/29 ก.พ. ไม่ล้นไป มี.ค.
        function addMonthsNoOverflow(iso, months) {
            const [y, m, d] = iso.split('-').map(Number);
            const lastDay = new Date(y, m - 1 + months + 1, 0).getDate();
            return new Date(y, m - 1 + months, Math.min(d, lastDay));
        }

        function renderCompleteNote() {
            const next = completing.next;
            if (next.months === null) {
                completeNote.textContent = next.none;
                return;
            }
            if (!completeDate.value) {
                completeNote.textContent = 'เลือกวันที่รถเข้าเช็คจริง เพื่อคำนวณคิวครั้งที่ ' + next.serviceNo;
                return;
            }
            const due = addMonthsNoOverflow(completeDate.value, next.months);
            completeNote.textContent = 'ปิดงานแล้วระบบจะสร้างคิวครั้งที่ ' + next.serviceNo + ' ให้อัตโนมัติ — ครบกำหนด '
                + due.getDate() + ' ' + thaiMonthsShort[due.getMonth()] + ' ' + String(due.getFullYear() + 543).slice(-2)
                + ' (อีก ' + next.months + ' เดือนนับจากวันที่รถเข้า)';
        }

        completeDate.addEventListener('input', () => completing && renderCompleteNote());

        window.openComplete = function (id) {
            const item = completable[id];
            if (!item) return;

            completing = item;
            completeForm.action = item.url;
            document.getElementById('completeWho').textContent = item.name;
            document.getElementById('completeMileage').value = '';

            const today = isoDate(new Date());
            completeDate.min = item.minDate;
            completeDate.max = today;
            completeDate.value = today;
            renderCompleteNote();

            completeDialog.showModal();
        };

        document.getElementById('completeClose').addEventListener('click', () => completeDialog.close());
        document.getElementById('completeCancel').addEventListener('click', () => completeDialog.close());

        @if ($canEdit)
            @php
                $fullEdit = auth()->user()->canEditAppointments();
                $editable = $appointments->filter(fn ($a) => auth()->user()->canEditAppointment($a))->mapWithKeys(fn ($a) => [$a->id => [
                    'name' => $a->fullName(),
                    'url' => route('appointments.update', $a),
                    'fromSales' => $a->isFromSales(),
                    // ยกเลิกการยืนยันได้เฉพาะคิวจากฝ่ายขายที่ยืนยันแล้วและยังไม่ปิด (sa ที่แค่เลื่อนนัดไม่ได้สิทธิ์นี้)
                    'unconfirmUrl' => $fullEdit && $a->isFromSales() && $a->confirmed_at && ! $a->completed_at && ! $a->declined_at
                        ? route('appointments.unconfirm', $a) : null,
                    'title_id' => $a->title_id,
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                    'phone' => phone_format($a->phone),
                    'phone2' => phone_format($a->phone2),
                    'vin' => $a->vin,
                    'delivered_at' => $a->delivered_at?->toDateString(),
                    'model_id' => $a->model_id,
                    'model_name' => $a->model_name,
                    'submodel_name' => $a->submodel_name,
                    'mileage' => $a->mileage,
                    'appointment_date' => $a->appointment_at->toDateString(),
                    'appointment_time' => $a->appointment_at->format('H:i'),
                    'note' => $a->note,
                ]]);
            @endphp
            const editable = @json($editable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            const editDialog = document.getElementById('editDialog');
            const editPicker = makeCarPicker({
                vin: document.getElementById('editVin'),
                model: document.getElementById('editModel'),
                submodel: document.getElementById('editSubmodel'),
                hint: document.getElementById('editVinStatus'),
            });

            // keep = เปิดซ้ำหลังกรอกผิด ใช้ค่าที่พิมพ์ไว้ (old) แทนค่าในฐานข้อมูล
            window.openEdit = function (id, keep) {
                const item = editable[id];
                if (!item) return;
                const values = keep ? { ...item, ...keep } : item;
                const set = (elId, value) => document.getElementById(elId).value = value ?? '';

                document.getElementById('editForm').action = item.url;
                set('editId', id);
                set('editTitle', values.title_id);
                set('editFirst', values.first_name);
                set('editLast', values.last_name);
                set('editPhone', values.phone);
                set('editPhone2', values.phone2);
                set('editVin', values.vin);
                set('editDelivered', values.delivered_at);
                set('editDate', values.appointment_date);
                set('editMileage', values.mileage);
                set('editNote', values.note);

                // นัดที่คีย์เอง: แสดงรุ่นที่บันทึกไว้ แล้วค้น VIN อีกรอบ (เจอในระบบขายจะล็อกรุ่นไว้)
                editPicker.reset();
                editPicker.select(values.model_id, item.model_name, values.submodel_name);
                if (!item.fromSales) editPicker.lookup();

                // เวลาเดิมอาจไม่อยู่ในช่วงเวลาทำการ — เพิ่มตัวเลือกให้ ไม่งั้นค่าหายตอนบันทึก
                const time = document.getElementById('editTime');
                const wanted = values.appointment_time;
                if (wanted && ![...time.options].some((o) => o.value === wanted)) time.add(new Option(wanted + ' น.', wanted), 0);
                time.value = wanted;

                // คิวจากฝ่ายขาย: ซ่อนและล็อกข้อมูลลูกค้า/รถ (fieldset ที่ disabled จะไม่ถูกส่งไป)
                const customer = document.getElementById('editCustomer');
                customer.disabled = item.fromSales;
                customer.hidden = item.fromSales;
                document.getElementById('editFromSales').hidden = !item.fromSales;

                const unconfirm = document.getElementById('editUnconfirm');
                unconfirm.hidden = !item.unconfirmUrl;
                unconfirm.onclick = () => {
                    if (!confirm('ยกเลิกการยืนยันนัดของ ' + item.name + ' ?\nคิวจะกลับไปอยู่ในรายการต้องโทรหา และวันนัดกลับเป็นวันครบกำหนดเดิม')) return;
                    const form = document.getElementById('unconfirmForm');
                    form.action = item.unconfirmUrl;
                    form.submit();
                };

                editDialog.showModal();
            };

            @if ($errors->edit->any() && old('_edit_id'))
                openEdit(@js((int) old('_edit_id')), @js(collect(old())->except(['_token', '_method', '_edit_id'])->all()));
            @endif
        @endif
    </script>
@endpush
