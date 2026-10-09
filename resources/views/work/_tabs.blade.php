{{-- สลับมุมมองปฏิทินงานช่าง: รายวัน (ทุกช่าง วันเดียว) / รายคน (ช่างคนเดียว หลายวัน) --}}
<div class="view-switch work-tabs">
    <a @class(['chip-link', 'active' => request()->routeIs('work.index')]) href="{{ route('work.index') }}">รายวัน</a>
    <a @class(['chip-link', 'active' => request()->routeIs('work.person')]) href="{{ route('work.person') }}">รายคน</a>
</div>
