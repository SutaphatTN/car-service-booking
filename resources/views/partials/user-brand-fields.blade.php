{{-- ช่องเลือกยี่ห้อของผู้ใช้ (ใช้ร่วมกันทั้งหน้าลงทะเบียนและหน้าแก้ไข) --}}
@php
    $selectedBrandId = (int) old('brand_id', $currentBrandId ?? '');
    $selectedExtra = array_map('intval', old('extra_brands', $currentExtraBrandIds ?? []));
@endphp

<label class="field" style="margin-top:14px">ยี่ห้อประจำตัว
    <select name="brand_id" class="@error('brand_id') is-invalid @enderror" required>
        <option value="">-- เลือก --</option>
        @foreach ($brands as $brand)
            <option value="{{ $brand->id }}" @selected($selectedBrandId === $brand->id)>{{ $brand->name }}</option>
        @endforeach
    </select>
    @error('brand_id')
        <span class="err">{{ $message }}</span>
    @else
        <span class="hint-inline">ยี่ห้อที่เห็นทันทีหลังเข้าสู่ระบบ</span>
    @enderror
</label>

@if ($brands->count() > 1)
    <div class="field" style="margin-top:14px">
        ยี่ห้ออื่นที่สลับไปดูได้
        <div class="brand-picker">
            @foreach ($brands as $brand)
                <label class="check">
                    <input type="checkbox" name="extra_brands[]" value="{{ $brand->id }}"
                           @checked(in_array($brand->id, $selectedExtra, true))>
                    {{ $brand->name }}
                </label>
            @endforeach
        </div>
        @error('extra_brands.*')
            <span class="err">{{ $message }}</span>
        @else
            <span class="hint-inline">
                ไม่ติ๊กเลย = เห็นเฉพาะยี่ห้อประจำตัว · ยี่ห้อประจำตัวจะถูกรวมให้อยู่แล้ว ติ๊กซ้ำหรือไม่ก็ได้
            </span>
        @enderror
    </div>
@endif
