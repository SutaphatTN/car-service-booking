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

                <label class="field">เลขไมล์ (กม.)
                    <input name="mileage" value="{{ old('mileage') }}" inputmode="numeric" placeholder="45000"
                           class="@error('mileage') is-invalid @enderror" required>
                    @error('mileage') <span class="err">{{ $message }}</span> @enderror
                </label>
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
        </div>

        <div class="modal-foot">
            <button type="button" class="ghost" id="cancelBtn">ยกเลิก</button>
            <button type="submit" class="primary">บันทึก</button>
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

        @if ($errors->any())
            dialog.showModal();
        @endif
    </script>
@endpush
