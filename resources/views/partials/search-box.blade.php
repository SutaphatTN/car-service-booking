{{-- ช่องค้นหา ใช้ร่วมกันทั้งหน้ารายการนัดหมายและหน้าต้องโทรหา --}}
@php
    $hidden = $hidden ?? [];
    $clearUrl = $action.($hidden ? '?'.http_build_query($hidden) : '');
@endphp

<form method="GET" action="{{ $action }}" class="search-box" role="search">
    @foreach ($hidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="search-field">
        <span class="search-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>
            </svg>
        </span>

        <input type="text" name="q" value="{{ $search }}" autocomplete="off"
               aria-label="ค้นหาชื่อ เบอร์โทร เลขตัวถัง หรือรุ่นรถ"
               placeholder="ค้นหาชื่อ เบอร์ VIN รุ่น">

        @if ($search)
            <a class="search-clear" href="{{ $clearUrl }}" title="ล้างคำค้น" aria-label="ล้างคำค้น">&times;</a>
        @endif
    </div>

    <button type="submit" class="ghost search-go">ค้นหา</button>
</form>
