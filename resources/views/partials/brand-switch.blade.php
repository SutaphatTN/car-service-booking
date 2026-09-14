{{-- ปุ่มสลับยี่ห้อรถ อยู่ในเมนูบัญชีมุมขวาบน --}}
@php
    $switchBrands = auth()->user()->accessibleBrands();
    $activeBrandId = current_brand_id();
    $switched = app(App\Support\CurrentBrand::class)->isSwitched();
@endphp

@if ($switchBrands->count() > 1)
    <div class="brand-switch">
        <div class="brand-switch-head">
            ยี่ห้อที่ดูอยู่
            @if ($switched)
                <span class="badge warn">สลับอยู่</span>
            @endif
        </div>

        <div class="brand-switch-list">
            @foreach ($switchBrands as $switchBrand)
                <form method="POST" action="{{ route('brand.switch') }}">
                    @csrf
                    <input type="hidden" name="brand_id" value="{{ $switchBrand->id }}">
                    <button type="submit" @class(['brand-switch-btn', 'active' => $switchBrand->id === $activeBrandId])>
                        {{ $switchBrand->name }}
                    </button>
                </form>
            @endforeach
        </div>

        @if ($switched)
            <form method="POST" action="{{ route('brand.reset') }}">
                @csrf
                <button type="submit" class="brand-switch-reset">กลับไปยี่ห้อประจำตัว</button>
            </form>
        @endif
    </div>
@endif
