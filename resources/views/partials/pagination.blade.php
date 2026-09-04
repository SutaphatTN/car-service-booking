{{-- แถบแบ่งหน้า ใช้กับ LengthAwarePaginator --}}
@if ($paginator->total() > 0)
    @php
        $last = $paginator->lastPage();
        $current = $paginator->currentPage();
        // แสดงเลขหน้าครั้งละ 5 หน้า โดยพยายามให้หน้าปัจจุบันอยู่ตรงกลาง
        $start = max(1, min($current - 2, $last - 4));
        $end = min($last, max($current + 2, 5));
    @endphp

    <nav class="pagination">
        <div class="pagination-info">แสดง {{ baht($paginator->firstItem()) }}-{{ baht($paginator->lastItem()) }} จาก {{ baht($paginator->total()) }} รายการ</div>

        @if ($paginator->hasPages())
            <div class="pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="page disabled">&lsaquo; ก่อนหน้า</span>
                @else
                    <a class="page" rel="prev" href="{{ $paginator->previousPageUrl() }}">&lsaquo; ก่อนหน้า</a>
                @endif

                @if ($start > 1)
                    <a class="page" href="{{ $paginator->url(1) }}">1</a>
                    @if ($start > 2)
                        <span class="page gap">…</span>
                    @endif
                @endif

                @foreach (range($start, $end) as $page)
                    @if ($page === $current)
                        <span class="page active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($end < $last)
                    @if ($end < $last - 1)
                        <span class="page gap">…</span>
                    @endif
                    <a class="page" href="{{ $paginator->url($last) }}">{{ $last }}</a>
                @endif

                @if ($paginator->hasMorePages())
                    <a class="page" rel="next" href="{{ $paginator->nextPageUrl() }}">ถัดไป &rsaquo;</a>
                @else
                    <span class="page disabled">ถัดไป &rsaquo;</span>
                @endif
            </div>
        @endif
    </nav>
@endif
