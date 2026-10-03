@extends('layouts.app')

@section('title', 'ข้อมูลช่าง')

@php
    // แก้ไขแล้วกรอกผิด — เปิดป็อปอัปแก้ไขของช่างคนเดิมค้างไว้ให้แก้ต่อ
    $editId = old('_edit_id');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h2>ข้อมูลช่าง</h2>
            <p class="muted">
                ช่างของยี่ห้อ {{ current_brand()?->name }} ทั้งหมด {{ baht($technicians->count()) }} คน · เห็นได้เฉพาะสิทธิ์ Admin
            </p>
        </div>
        <button type="button" class="primary" onclick="openTechnician()">+ เพิ่มช่าง</button>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>ชื่อช่าง</th>
                    <th class="right" style="width:140px">งานเดือนนี้</th>
                    <th class="right" style="width:140px">งานทั้งหมด</th>
                    <th style="width:130px">เพิ่มเมื่อ</th>
                    <th class="right" style="width:170px">จัดการ</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($technicians as $technician)
                    <tr>
                        <td><b>{{ $technician->name }}</b></td>
                        <td class="right">{{ baht($technician->month_jobs_count) }}</td>
                        <td class="right">{{ baht($technician->jobs_count) }}</td>
                        <td class="sub">{{ $technician->created_at ? thai_date($technician->created_at, short: true) : '-' }}</td>
                        <td class="right">
                            <div class="row-actions" style="justify-content:flex-end">
                                <button type="button" class="ghost"
                                        onclick="openTechnician({{ $technician->id }}, @js($technician->name))">แก้ไข</button>
                                <form method="POST" action="{{ route('technicians.destroy', $technician) }}"
                                      onsubmit="return confirm(@js('ลบช่าง '.$technician->name.' ?\nงานที่เคยลงไว้ยังอยู่ในปฏิทิน แต่จะเลือกช่างคนนี้ลงงานใหม่ไม่ได้แล้ว'))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ghost danger">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">ยังไม่มีข้อมูลช่าง กด "+ เพิ่มช่าง" เพื่อเริ่มต้น</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ป็อปอัปเดียวใช้ทั้งเพิ่มและแก้ไข — JS สลับ action / method / หัวข้อให้ --}}
    <dialog id="technicianDialog" class="modal" style="width:min(440px, calc(100vw - 32px))">
        <form method="POST" id="technicianForm" action="{{ route('technicians.store') }}">
            @csrf
            <input type="hidden" name="_method" id="technicianMethod" value="POST">
            <input type="hidden" name="_edit_id" id="technicianEditId" value="">

            <div class="modal-head">
                <h2 id="technicianTitle">เพิ่มช่าง</h2>
                <button type="button" class="modal-close" onclick="technicianDialog.close()" aria-label="ปิด">&times;</button>
            </div>

            <div class="modal-body">
                <label class="field">ชื่อช่าง
                    <input name="name" id="technicianName" maxlength="100" autocomplete="off"
                           value="{{ old('name') }}" class="@error('name') is-invalid @enderror" required>
                    @error('name') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="ghost" onclick="technicianDialog.close()">ยกเลิก</button>
                <button type="submit" class="primary" data-busy="กำลังบันทึก…">บันทึก</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        const technicianDialog = document.getElementById('technicianDialog');
        const storeUrl = @js(route('technicians.store'));
        const updateUrl = @js(route('technicians.update', ['technician' => '__ID__']));

        // ไม่ส่ง id = เพิ่มใหม่, ส่ง id = แก้ไขช่างคนนั้น ($keepValue = เปิดซ้ำหลังกรอกผิด ไม่ล้างค่าที่พิมพ์ไว้)
        window.openTechnician = function (id, name, keepValue) {
            const form = document.getElementById('technicianForm');
            const nameInput = document.getElementById('technicianName');

            form.action = id ? updateUrl.replace('__ID__', id) : storeUrl;
            document.getElementById('technicianMethod').value = id ? 'PATCH' : 'POST';
            document.getElementById('technicianEditId').value = id || '';
            document.getElementById('technicianTitle').textContent = id ? 'แก้ไขชื่อช่าง' : 'เพิ่มช่าง';
            if (!keepValue) nameInput.value = name || '';

            technicianDialog.showModal();
            nameInput.focus();
        };

        @if ($errors->any())
            openTechnician(@js($editId ? (int) $editId : null), null, true);
        @endif
    </script>
@endpush
