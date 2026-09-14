{{-- เบอร์โทรของลูกค้า — เบอร์หลักกับเบอร์สำรอง ใช้ร่วมกันทุกหน้า --}}
@php
    /** เบอร์ที่ฝ่ายขายกรอกผิดรูปแบบ ต้องมองออกทันทีว่าโทรไม่ได้ */
    $primaryOk = $phone && preg_match('/^0[0-9]{9}$/', $phone);
@endphp

@if ($phone)
    <a href="tel:{{ $phone }}" @class(['phone-bad' => ! $primaryOk])
       @if (! $primaryOk) title="เบอร์ไม่ครบ 10 หลัก ฝ่ายขายกรอกมาแบบนี้" @endif>
        {{ phone_format($phone) }}
    </a>
    @unless ($primaryOk)
        <span class="phone-warn">⚠</span>
    @endunless
@else
    <span class="sub no-phone">ไม่มีเบอร์</span>
@endif

@if ($phone2)
    <div class="sub">สำรอง <a href="tel:{{ $phone2 }}">{{ phone_format($phone2) }}</a></div>
@endif
