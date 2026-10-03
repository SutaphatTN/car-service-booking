{{-- สลับระหว่างรายงานแต่ละแบบ ใช้ร่วมกันทุกหน้าในเมนูรายงาน --}}
<div class="report-tabs">
    <a @class(['chip-link', 'active' => request()->routeIs('reports.index')])
       href="{{ route('reports.index') }}">รายงานนัดหมาย</a>
    <a @class(['chip-link', 'active' => request()->routeIs('reports.calls')])
       href="{{ route('reports.calls') }}">รายงานการโทร</a>
</div>
