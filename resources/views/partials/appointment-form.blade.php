{{-- ฟอร์มเพิ่มนัดหมาย (ใช้ร่วมกันทั้งหน้ารายการและหน้าปฏิทิน) --}}
<dialog id="addDialog" class="modal">
    <form method="POST" action="{{ route('appointments.store') }}">
        @csrf

        <div class="modal-head">
            <h2>เพิ่มนัดหมาย</h2>
            <button type="button" class="modal-close" id="closeBtn" aria-label="ปิด">&times;</button>
        </div>

        <div class="modal-body">
            <div class="grid cols-3">
                <label class="field">คำนำหน้า
                    <select name="title_id" class="@error('title_id') is-invalid @enderror" required>
                        <option value="">-- เลือก --</option>
                        @foreach ($titles as $title)
                            <option value="{{ $title->id }}" @selected(old('title_id') == $title->id)>{{ $title->name }}</option>
                        @endforeach
                    </select>
                    @error('title_id') <span class="err">{{ $message }}</span> @enderror
                </label>

                <label class="field">ชื่อ
                    <input name="first_name" value="{{ old('first_name') }}"
                           class="@error('first_name') is-invalid @enderror" required>
                    @error('first_name') <span class="err">{{ $message }}</span> @enderror
                </label>

                <label class="field">นามสกุล
                    <input name="last_name" value="{{ old('last_name') }}"
                           class="@error('last_name') is-invalid @enderror" required>
                    @error('last_name') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:12px">
                <label class="field">เบอร์โทร
                    <input id="phoneInput" name="phone" value="{{ phone_format(old('phone')) }}"
                           inputmode="numeric" autocomplete="off" maxlength="12" placeholder="088-4535-354"
                           class="@error('phone') is-invalid @enderror" required>
                    @error('phone')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">ใส่ตัวเลข 10 หลัก ระบบจะเติมขีดให้เอง</span>
                    @enderror
                </label>

                <label class="field">เบอร์สำรอง <span class="muted">(ไม่บังคับ)</span>
                    <input name="phone2" value="{{ phone_format(old('phone2')) }}"
                           inputmode="numeric" autocomplete="off" maxlength="12" placeholder="088-4535-354"
                           class="@error('phone2') is-invalid @enderror">
                    @error('phone2') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:12px">
                <label class="field">เลขไมล์ (กม.)
                    <input name="mileage" value="{{ old('mileage') }}" inputmode="numeric" placeholder="45000"
                           class="@error('mileage') is-invalid @enderror" required>
                    @error('mileage') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid cols-2" style="margin-top:12px">
                <label class="field">เลขตัวถัง VIN
                    <input id="vinInput" name="vin" value="{{ old('vin') }}" maxlength="17"
                           autocomplete="off" spellcheck="false" placeholder="LGWEF4A50NH123456"
                           class="vin-input @error('vin') is-invalid @enderror" required>
                    @error('vin')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">17 หลัก ระบบเปลี่ยนเป็นตัวพิมพ์ใหญ่ให้เอง</span>
                    @enderror
                </label>

                <label class="field">วันที่ส่งมอบจริง <span class="muted">(ไม่บังคับ)</span>
                    <input type="date" name="delivered_at" value="{{ old('delivered_at') }}"
                           class="@error('delivered_at') is-invalid @enderror">
                    @error('delivered_at')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">วันที่ลูกค้ารับรถจริง เว้นว่างได้ถ้ายังไม่ทราบ</span>
                    @enderror
                </label>
            </div>

            {{-- รุ่นรถ — VIN เจอในระบบขายจะเติมให้และล็อกไว้ ไม่เจอค่อยเลือกเอง --}}
            <div class="grid cols-2" style="margin-top:12px">
                <label class="field">รุ่นหลัก
                    <select name="model_id" id="modelSelect" data-old="{{ old('model_id') }}"
                            class="@error('model_id') is-invalid @enderror">
                        <option value="">-- เลือก --</option>
                    </select>
                    @error('model_id') <span class="err">{{ $message }}</span> @enderror
                    <span class="hint-inline" id="vinStatus"></span>
                </label>

                <label class="field">รุ่นย่อย <span class="muted">(ไม่บังคับ)</span>
                    <select name="submodel_name" id="submodelSelect" data-old="{{ old('submodel_name') }}"
                            class="@error('submodel_name') is-invalid @enderror">
                        <option value="">-- เลือก --</option>
                    </select>
                    @error('submodel_name') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            {{-- รถมีคิวจากฝ่ายขายค้างอยู่ — ให้เจ้าหน้าที่เลือกเองทุกครั้ง ไม่ตั้งค่าไว้ให้ --}}
            <div class="alert info" id="queueChoice" style="margin:12px 0 0" hidden>
                <div id="queueText"></div>
                <div class="reason-list">
                    <label class="reason-option">
                        <input type="radio" name="use_queue" id="useQueueYes" value="">
                        <span>ลงนัดในคิวนี้ — ปิดงานแล้วระบบสร้างคิวครั้งถัดไปให้</span>
                    </label>
                    <label class="reason-option">
                        <input type="radio" name="use_queue" value="new" @checked(old('use_queue') === 'new')>
                        <span>สร้างนัดใหม่แยกต่างหาก — คิวเดิมยังอยู่ในรายการต้องโทรหา</span>
                    </label>
                </div>
                @error('use_queue') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="grid cols-2" style="margin-top:12px">
                <label class="field">วันที่นัด
                    <input type="date" id="dateInput" name="appointment_date"
                           value="{{ old('appointment_date', $defaultDate ?? now()->toDateString()) }}"
                           class="@error('appointment_date') is-invalid @enderror" required>
                    @error('appointment_date') <span class="err">{{ $message }}</span> @enderror
                </label>

                <label class="field">เวลานัด
                    <select name="appointment_time" class="@error('appointment_time') is-invalid @enderror" required>
                        @foreach ($timeSlots as $group => $slots)
                            <optgroup label="{{ $group }}">
                                @foreach ($slots as $slot)
                                    <option value="{{ $slot }}" @selected(old('appointment_time', '09:00') === $slot)>
                                        {{ $slot }} น.
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('appointment_time')
                        <span class="err">{{ $message }}</span>
                    @else
                        <span class="hint-inline">เวลาแบบ 24 ชั่วโมง เช่น 13:30 น. คือบ่ายโมงครึ่ง</span>
                    @enderror
                </label>
            </div>

            <label class="field" style="display:block;margin-top:12px">หมายเหตุ <span class="muted">(ไม่บังคับ)</span>
                <textarea name="note" rows="3" maxlength="1000" placeholder="เช่น เช็คระยะ 10,000 กม."
                          class="@error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                @error('note') <span class="err">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="modal-foot">
            <button type="button" class="ghost" id="cancelBtn">ยกเลิก</button>
            <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึก</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        const dialog = document.getElementById('addDialog');
        const dateInput = document.getElementById('dateInput');
        const phoneInput = document.getElementById('phoneInput');

        // เรียกจากปุ่ม "เพิ่มข้อมูล" ส่งวันที่มาด้วยได้ (หน้าปฏิทินใช้วันที่ที่เลือกอยู่)
        window.openAppointmentDialog = function (date) {
            if (date) dateInput.value = date;
            dialog.showModal();
        };

        document.getElementById('closeBtn').addEventListener('click', () => dialog.close());
        document.getElementById('cancelBtn').addEventListener('click', () => dialog.close());

        // เบอร์โทร: รับตัวเลขไม่เกิน 10 หลัก แล้วเติมขีดเป็น 088-4535-354 ให้อัตโนมัติ
        function formatPhone(value) {
            const digits = value.replace(/\D/g, '').slice(0, 10);

            if (digits.length <= 3) return digits;
            if (digits.length <= 7) return digits.slice(0, 3) + '-' + digits.slice(3);

            return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7);
        }

        phoneInput.addEventListener('input', () => {
            // นับตัวเลขที่อยู่ก่อนเคอร์เซอร์ไว้ เพื่อวางเคอร์เซอร์กลับที่เดิมหลังเติมขีด
            const digitsBeforeCaret = phoneInput.value
                .slice(0, phoneInput.selectionStart)
                .replace(/\D/g, '').length;

            phoneInput.value = formatPhone(phoneInput.value);

            let caret = 0;
            let seen = 0;
            while (caret < phoneInput.value.length && seen < digitsBeforeCaret) {
                if (/\d/.test(phoneInput.value[caret])) seen++;
                caret++;
            }
            phoneInput.setSelectionRange(caret, caret);
        });

        phoneInput.value = formatPhone(phoneInput.value);

        // VIN: พิมพ์เล็กหรือใส่เว้นวรรค/ขีดมาก็แปลงให้เป็นตัวพิมพ์ใหญ่ล้วนทันที
        const vinInput = document.getElementById('vinInput');

        vinInput.addEventListener('input', () => {
            const caret = vinInput.selectionStart;
            const cleaned = vinInput.value.toUpperCase().replace(/[^A-HJ-NPR-Z0-9]/g, '');

            if (cleaned !== vinInput.value) {
                vinInput.value = cleaned;
                vinInput.setSelectionRange(caret, caret);
            }
        });

        /*
         * รุ่นหลัก/รุ่นย่อยที่ผูกกับช่อง VIN — ใช้ทั้งฟอร์มเพิ่มนัดและฟอร์มแก้ไข
         * VIN ครบ 17 หลักแล้วไปค้นในระบบขาย เจอ = เติมรุ่นแล้วล็อก ไม่เจอ = ให้เลือกเอง
         * (ฝั่งเซิร์ฟเวอร์ค้นซ้ำตอนบันทึกเสมอ ตรงนี้แค่ช่วยให้เห็นก่อนกดบันทึก)
         */
        const carModels = @json($carModels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        const vinLookupUrl = @json(route('appointments.vin-lookup'));

        window.makeCarPicker = function ({ vin, model, submodel, hint, onLookup }) {
            const idleHint = 'กรอก VIN ครบ 17 หลัก ระบบจะหารุ่นจากระบบขายให้';
            const has = (select, value) => [...select.options].some((o) => o.value === String(value));

            carModels.forEach((m) => model.add(new Option(m.name, m.id)));

            function fillSubmodels(wanted) {
                const found = carModels.find((m) => String(m.id) === model.value);
                submodel.options.length = 1;
                (found ? found.submodels : []).forEach((s) => submodel.add(new Option(s, s)));
                // รุ่นย่อยจากระบบขายที่เลิกขายไปแล้วไม่อยู่ในรายการ — เพิ่มให้ ไม่งั้นค่าหาย
                if (wanted && !has(submodel, wanted)) submodel.add(new Option(wanted, wanted));
                submodel.value = wanted || '';
            }

            function select(modelId, modelName, sub) {
                if (modelId && !has(model, modelId)) model.add(new Option(modelName || 'รุ่น #' + modelId, modelId));
                model.value = modelId ? String(modelId) : '';
                fillSubmodels(sub);
            }

            let locked = false;
            function lock(on) {
                // เพิ่งปลดจากรุ่นของรถคันก่อน (แก้ VIN) — ล้างทิ้ง ไม่ให้ติดรุ่นของคันอื่นไป
                if (locked && !on) select(null, null, null);
                locked = on;
                model.disabled = submodel.disabled = on;
                model.required = !on;
            }

            // พิมพ์เร็ว ๆ คำตอบอาจกลับมาไม่ตามลำดับ — ใช้แค่คำตอบของคำค้นล่าสุด
            let seq = 0;
            async function lookup() {
                const mine = ++seq;
                const value = vin.value;

                if (value.length !== 17) {
                    lock(false);
                    hint.textContent = idleHint;
                    onLookup?.(null);
                    return;
                }

                hint.textContent = 'กำลังค้นหา VIN ในระบบขาย…';
                let data = null;
                try {
                    const res = await fetch(vinLookupUrl + '?vin=' + encodeURIComponent(value), {
                        headers: { Accept: 'application/json' },
                    });
                    if (res.ok) data = await res.json();
                } catch (e) { /* ค้นไม่ได้ก็ให้เลือกรุ่นเอง */ }

                if (mine !== seq) return;

                if (data?.found) {
                    select(data.car.model_id, data.car.model, data.car.submodel);
                    lock(true);
                    hint.textContent = 'พบรถในระบบขาย — ดึงรุ่นมาให้แล้ว';
                } else if (data?.stock) {
                    select(data.stock.model_id, data.stock.model, data.stock.submodel);
                    lock(true);
                    hint.textContent = 'ไม่มีใบขาย แต่พบเลขตัวถังในสต็อกของระบบขาย — ดึงรุ่นมาให้แล้ว';
                } else {
                    lock(false);
                    hint.textContent = data
                        ? 'ไม่พบ VIN นี้ในระบบขาย กรุณาเลือกรุ่นเอง'
                        : 'ค้นหา VIN ไม่สำเร็จ กรุณาเลือกรุ่นเอง';
                }

                onLookup?.(data?.found ? data : null);
            }

            model.addEventListener('change', () => fillSubmodels(''));
            vin.addEventListener('input', lookup);
            hint.textContent = idleHint;

            // เปิดฟอร์มให้แถวใหม่ — ทิ้งสถานะของแถวก่อน (รวมคำค้นที่ยังค้างอยู่)
            function reset() {
                seq++;
                locked = false;
                lock(false);
            }

            return { select, lookup, reset };
        };

        // ฟอร์มเพิ่มนัด: เจอรถแล้วเติมช่องที่ยังว่าง และถามเรื่องคิวเดิมถ้ามี
        const addForm = dialog.querySelector('form');
        const queueChoice = document.getElementById('queueChoice');
        const queueRadios = queueChoice.querySelectorAll('input[name="use_queue"]');
        const useQueueYes = document.getElementById('useQueueYes');
        const oldUseQueue = @json(old('use_queue'));

        const addPicker = makeCarPicker({
            vin: vinInput,
            model: document.getElementById('modelSelect'),
            submodel: document.getElementById('submodelSelect'),
            hint: document.getElementById('vinStatus'),
            onLookup(data) {
                const queue = data?.queue ?? null;

                queueChoice.hidden = !queue;
                // ช่องที่ซ่อนอยู่ห้าม required ไม่งั้นกดบันทึกไม่ได้โดยไม่รู้สาเหตุ
                queueRadios.forEach((r) => r.required = !!queue);

                if (queue) {
                    useQueueYes.value = queue.id;
                    if (oldUseQueue === String(queue.id)) useQueueYes.checked = true;
                    document.getElementById('queueText').textContent =
                        'รถคันนี้มีคิวเช็คระยะครั้งที่ ' + queue.service_no + ' จากระบบขายอยู่แล้ว (ครบกำหนด '
                        + queue.due + ' · ' + queue.status + ') จะลงนัดนี้แบบไหน';
                } else {
                    queueRadios.forEach((r) => r.checked = false);
                }

                if (!data) return;

                // เติมเฉพาะช่องที่ยังว่าง — ที่เจ้าหน้าที่พิมพ์ไว้แล้วไม่ทับ
                const fill = (name, value) => {
                    const el = addForm.elements[name];
                    if (el && !el.value && value) el.value = value;
                };
                fill('title_id', data.car.title_id);
                fill('first_name', data.car.first_name);
                fill('last_name', data.car.last_name);
                fill('phone', data.car.phone);
                fill('phone2', data.car.phone2);
                fill('delivered_at', data.car.delivered_at);
            },
        });

        // กรอกผิดแล้วเด้งกลับมา — คืนรุ่นที่เลือกไว้ แล้วค้น VIN เดิมอีกรอบ
        addPicker.select(
            document.getElementById('modelSelect').dataset.old,
            null,
            document.getElementById('submodelSelect').dataset.old
        );
        if (vinInput.value) addPicker.lookup();

        @if ($errors->any())
            dialog.showModal();
        @endif
    </script>
@endpush
