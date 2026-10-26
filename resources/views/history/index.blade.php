@extends('layouts.app')

@section('title', 'ประวัติการนัดหมาย')

@section('content')
    <div class="page-head">
        <div>
            <h2>ประวัติการนัดหมาย</h2>
            <p class="muted">ดูย้อนหลังทุกนัด รวมงานที่ปิดแล้ว · แก้ได้ทุกช่องเผื่อกดผิด · เห็นได้เฉพาะสิทธิ์ Admin</p>
        </div>
        @include('partials.search-box', [
            'action' => route('history.index'),
            'hidden' => $status !== 'all' ? ['status' => $status] : [],
        ])
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        <div class="status-bar">
            @foreach ($statuses as $key)
                <a @class(['chip-link', 'st-'.$key, 'active' => $key === $status])
                   href="{{ route('history.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search])) }}">
                    <span class="st-dot" aria-hidden="true"></span>
                    {{ App\Models\Appointment::statusLabel($key) }}
                </a>
            @endforeach
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th style="width:150px">วันนัด</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width:185px">รถ</th>
                    <th style="width:130px">สถานะ</th>
                    <th style="width:150px">โทรล่าสุด</th>
                    <th style="width:170px">ปิดงาน / ปิดคิว</th>
                    <th class="right" style="width:90px">จัดการ</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>
                            <b>{{ thai_date($appointment->appointment_at, short: true) }}</b>
                            <div class="sub">{{ $appointment->appointment_at->format('H:i') }} น.</div>
                        </td>
                        <td>
                            <b>{{ $appointment->fullName() }}</b>
                            <div class="sub">{{ phone_format($appointment->phone) ?: '-' }}</div>
                        </td>
                        <td>
                            @include('partials.car-cell')
                            @if ($appointment->service_no)
                                <div class="sub">ครั้งที่ {{ $appointment->service_no }}</div>
                            @endif
                        </td>
                        <td><span class="badge status-{{ $appointment->statusKey() }}">{{ $appointment->status() }}</span></td>
                        <td class="sub">
                            @if ($appointment->call_logs_max_created_at)
                                {{ thai_date($appointment->call_logs_max_created_at, short: true) }}
                                <div>โทร {{ $appointment->call_logs_count }} ครั้ง</div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="sub">
                            @if ($appointment->completed_at)
                                รถเข้า {{ thai_date($appointment->serviced_at ?? $appointment->completed_at, short: true) }}
                                <div>กดปิด {{ thai_date($appointment->completed_at, short: true) }} {{ $appointment->completed_at->format('H:i') }}</div>
                            @elseif ($appointment->declined_at)
                                ปิดคิว {{ thai_date($appointment->declined_at, short: true) }}
                                <div>{{ $appointment->declineReasonLabel() }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="right">
                            <a href="{{ route('history.edit', $appointment) }}"><button type="button" class="ghost">แก้ไข</button></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            {{ $search ? 'ไม่พบนัดหมายที่ตรงกับ "'.$search.'"' : 'ยังไม่มีนัดหมาย' }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $appointments])
    </div>
@endsection
