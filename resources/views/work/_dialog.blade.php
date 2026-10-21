{{-- ป็อปอัปเพิ่ม/แก้ไข/ดูงานช่าง + สคริปต์ — ใช้ร่วมกันทั้งมุมมองรายวันและรายคน --}}
@use('App\Models\TechnicianJob')

@php
    // ข้อมูลงานสำหรับป็อปอัปแก้ไข/ดูรายละเอียด
    $jobData = $allJobs->mapWithKeys(fn (TechnicianJob $job) => [$job->id => [
        'id' => $job->id,
        'technician_id' => $job->technician_id,
        'technician' => $job->technician->name,
        'work_date' => $job->work_date->toDateString(),
        'start' => $job->startLabel(),
        'end' => $job->endLabel(),
        'type' => $job->job_type,
        'plate' => $job->license_plate,
        'note' => $job->note,
        'creator' => $job->creator?->name,
        'editable' => $job->editableBy(auth()->user()),
    ]]);
@endphp

    {{-- ป็อปอัปเดียวใช้ทั้งเพิ่ม แก้ไข และดูรายละเอียด (งานที่ไม่มีสิทธิ์แก้) --}}
    <dialog id="jobDialog" class="modal" style="width:min(560px, calc(100vw - 32px))">
        <form method="POST" id="jobForm" action="{{ route('work.store') }}">
            @csrf
            <input type="hidden" name="_method" id="jobMethod" value="POST">
            <input type="hidden" name="_job_id" id="jobId" value="">
            {{-- บันทึกเสร็จกลับมาหน้าเดิม (รายวัน/รายคน) --}}
            <input type="hidden" name="_back" value="{{ old('_back', url()->full()) }}">

            <div class="modal-head">
                <h2 id="jobTitle">เพิ่มงานช่าง</h2>
                <button type="button" class="modal-close" onclick="jobDialog.close()" aria-label="ปิด">&times;</button>
            </div>

            <div class="modal-body">
                <fieldset id="jobFields" class="plain">
                    <div class="grid cols-2">
                        <label class="field">ช่าง
                            <select name="technician_id" id="jobTechnician" class="@error('technician_id') is-invalid @enderror" required>
                                <option value="">-- เลือกช่าง --</option>
                                @foreach ($technicians as $technician)
                                    {{-- ช่างที่ลบไปแล้วโผล่เฉพาะตอนเปิดดูงานเก่าของเขา เลือกใหม่ไม่ได้ --}}
                                    <option value="{{ $technician->id }}" @if ($technician->trashed()) data-trashed hidden @endif
                                        @selected((string) old('technician_id') === (string) $technician->id)>{{ $technician->name }}</option>
                                @endforeach
                            </select>
                            @error('technician_id') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">วันที่
                            <input type="date" name="work_date" id="jobDate" value="{{ old('work_date', $formDate) }}"
                                   @unless ($canBackdate) min="{{ today()->toDateString() }}" @endunless
                                   class="@error('work_date') is-invalid @enderror" required>
                            @error('work_date')
                                <span class="err">{{ $message }}</span>
                            @else
                                @unless ($canBackdate)
                                    <span class="hint-inline">ลงย้อนหลังได้เฉพาะ Admin</span>
                                @endunless
                            @enderror
                        </label>
                    </div>

                    <div class="grid cols-2" style="margin-top:12px">
                        <label class="field">ตั้งแต่เวลา
                            <select name="start_time" id="jobStart" class="@error('start_time') is-invalid @enderror" required>
                                @foreach ($timeOptions as $time)
                                    <option value="{{ $time }}" @selected(old('start_time', '08:00') === $time)>{{ $time }} น.</option>
                                @endforeach
                            </select>
                            @error('start_time') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">ถึงเวลา
                            <select name="end_time" id="jobEnd" class="@error('end_time') is-invalid @enderror" required>
                                @foreach ($timeOptions as $time)
                                    <option value="{{ $time }}" @selected(old('end_time', '09:00') === $time)>{{ $time }} น.</option>
                                @endforeach
                            </select>
                            @error('end_time') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="field" style="margin-top:12px">ประเภทงาน
                        <div class="job-types">
                            @foreach (TechnicianJob::TYPES as $type)
                                <label class="job-type-option job-{{ $type }}">
                                    <input type="radio" name="job_type" value="{{ $type }}"
                                           @checked(old('job_type') === $type) required>
                                    {{ $type }}
                                </label>
                            @endforeach
                        </div>
                        @error('job_type') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid cols-2" style="margin-top:12px">
                        <label class="field">ป้ายทะเบียน <span class="muted">(ไม่บังคับ)</span>
                            <input name="license_plate" id="jobPlate" maxlength="50" autocomplete="off"
                                   value="{{ old('license_plate') }}" placeholder="กข 1234 พระนครศรีอยุธยา"
                                   class="@error('license_plate') is-invalid @enderror">
                            @error('license_plate') <span class="err">{{ $message }}</span> @enderror
                        </label>

                    </div>

                    {{-- รายละเอียดงานอาจยาว จึงให้เต็มแถวและหลายบรรทัด --}}
                    <label class="field" style="display:block;margin-top:12px">หมายเหตุ <span class="muted">(ไม่บังคับ)</span>
                        <textarea name="note" id="jobNote" rows="4" maxlength="1000"
                                  class="@error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                        @error('note') <span class="err">{{ $message }}</span> @enderror
                    </label>
                </fieldset>

                <p class="muted" id="jobReadonly" style="margin:14px 0 0;font-size:13px" hidden></p>
            </div>

            <div class="modal-foot">
                <button type="button" class="ghost danger modal-foot-start" id="jobDelete" hidden>ลบงานนี้</button>
                <button type="button" class="ghost" onclick="jobDialog.close()">ปิด</button>
                <button type="submit" class="primary" id="jobSave" data-busy="กำลังบันทึก…">บันทึก</button>
            </div>
        </form>
    </dialog>

    {{-- ฟอร์มลบแยกออกมา — ฟอร์มซ้อนกันไม่ได้ --}}
    <form method="POST" id="jobDeleteForm" hidden>
        @csrf
        @method('DELETE')
    </form>

@push('scripts')
    <script>
        const jobDialog = document.getElementById('jobDialog');
        const jobs = @js($jobData);
        const storeUrl = @js(route('work.store'));
        const jobUrl = @js(route('work.update', ['job' => '__ID__']));
        const formDate = @js($formDate);
        const range = { start: {{ $rangeStart }}, end: {{ $rangeEnd }} };
        const el = (id) => document.getElementById(id);

        function pad(minutes) {
            return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
        }

        function setType(type) {
            document.querySelectorAll('input[name="job_type"]').forEach((radio) => radio.checked = radio.value === type);
        }

        // ไม่ส่ง id = ลงงานใหม่ (ส่ง preset มาเติมช่าง/เวลาได้), ส่ง id = เปิดงานนั้น
        window.openJob = function (id, preset) {
            // แก้ไขแล้วกรอกผิด แต่งานไม่อยู่ในวันที่เปิดอยู่ — ยังต้องส่งไปที่งานเดิม ไม่ใช่สร้างใหม่
            const job = id ? (jobs[id] ?? { id: id, editable: true, creator: null }) : null;
            const editable = !job || job.editable;

            el('jobForm').action = job ? jobUrl.replace('__ID__', job.id) : storeUrl;
            el('jobMethod').value = job ? 'PATCH' : 'POST';
            el('jobId').value = job ? job.id : '';
            el('jobTitle').textContent = !job ? 'เพิ่มงานช่าง' : (editable ? 'แก้ไขงานช่าง' : 'รายละเอียดงานช่าง');

            if (!preset || !preset.keep) {
                el('jobTechnician').value = job ? job.technician_id : (preset?.technician ?? '');
                el('jobDate').value = job ? job.work_date : (preset?.date ?? formDate);
                el('jobStart').value = job ? job.start : (preset?.start ?? '08:00');
                el('jobEnd').value = job ? job.end : (preset?.end ?? '09:00');
                setType(job ? job.type : null);
                el('jobPlate').value = job ? (job.plate ?? '') : '';
                el('jobNote').value = job ? (job.note ?? '') : '';
            }

            el('jobFields').disabled = !editable;
            el('jobSave').hidden = !editable;
            el('jobDelete').hidden = !(job && editable);

            const info = el('jobReadonly');
            info.hidden = !job;
            if (job) {
                info.textContent = 'บันทึกโดย ' + (job.creator ?? '-')
                    + (editable ? '' : ' · แก้ไข/ลบได้เฉพาะคนที่ลงงานนี้ (และยังไม่ใช่งานย้อนหลัง) หรือ Admin');
            }

            jobDialog.showModal();
        };

        el('jobDelete').addEventListener('click', () => {
            const job = jobs[el('jobId').value];
            if (!job || !confirm('ลบงาน ' + job.type + ' ของ ' + job.technician + ' เวลา ' + job.start + '–' + job.end + ' น. ?')) return;

            const form = el('jobDeleteForm');
            form.action = jobUrl.replace('__ID__', job.id);
            form.submit();
        });

        // เลือกเวลาเริ่มแล้ว ถ้าเวลาเสร็จไม่อยู่หลังเวลาเริ่ม ขยับให้เป็นหนึ่งชั่วโมงถัดไป
        el('jobStart').addEventListener('change', () => {
            if (el('jobEnd').value <= el('jobStart').value) {
                const [h, m] = el('jobStart').value.split(':').map(Number);
                const next = pad(Math.min(h * 60 + m + 60, 22 * 60));
                if ([...el('jobEnd').options].some((o) => o.value === next)) el('jobEnd').value = next;
            }
        });

        // คลิกช่องว่างในแถว = ลงงานให้ช่าง/วันของแถวนั้น เริ่มตามเวลาที่คลิก (ปัดลงทีละครึ่งชั่วโมง)
        document.querySelectorAll('.work-track.can-add').forEach((track) => {
            track.addEventListener('click', (event) => {
                const box = track.getBoundingClientRect();
                const minutes = range.start + (event.clientX - box.left) / box.width * (range.end - range.start);
                const start = Math.max(range.start, Math.floor(minutes / 30) * 30);

                openJob(null, {
                    technician: track.dataset.technician, date: track.dataset.date,
                    start: pad(start), end: pad(Math.min(start + 60, 22 * 60)),
                });
            });
        });

        @if ($errors->any())
            openJob(@js(old('_job_id') ? (int) old('_job_id') : null), { keep: true });
        @elseif ($openAdd)
            openJob();
        @endif
    </script>
@endpush
