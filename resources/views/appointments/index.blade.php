@extends('layouts.app')

@section('title', 'รายการนัดหมาย')

@section('content')
    <div class="page-head">
        <div>
            <h2>รายการนัดหมาย</h2>
            <p class="muted">เรียงตามวันและเวลานัด · ทั้งหมด {{ baht($appointments->total()) }} รายการ</p>
        </div>
        <button type="button" class="primary" onclick="openAppointmentDialog()">+ เพิ่มข้อมูล</button>
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
                    <th style="width:130px">เบอร์โทร</th>
                    <th class="right" style="width:120px">เลขไมล์ (กม.)</th>
                    <th style="width:130px">ผู้บันทึก</th>
                    @if (auth()->user()->isSa())
                        <th class="right" style="width:80px">จัดการ</th>
                    @endif
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
                        </td>
                        <td>{{ phone_format($appointment->phone) }}</td>
                        <td class="right">{{ baht($appointment->mileage) }}</td>
                        <td class="sub">{{ $appointment->creator?->name ?? '-' }}</td>
                        @if (auth()->user()->isSa())
                            <td class="right">
                                <form method="POST" action="{{ route('appointments.destroy', $appointment) }}"
                                      data-name="{{ $appointment->fullName() }}"
                                      onsubmit="return confirm('ลบนัดหมายของ ' + this.dataset.name + ' ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ghost danger">ลบ</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isSa() ? 6 : 5 }}" class="empty">
                            ยังไม่มีนัดหมาย กด "เพิ่มข้อมูล" เพื่อเริ่มบันทึก
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $appointments])
    </div>

    @include('partials.appointment-form')
@endsection
