<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Support\CurrentBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** สลับยี่ห้อที่กำลังดูอยู่ — เลือกได้เฉพาะยี่ห้อที่บัญชีนั้นมีสิทธิ์ */
class BrandSwitchController extends Controller
{
    public function store(Request $request, CurrentBrand $currentBrand): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
        ], [
            'brand_id.required' => 'กรุณาเลือกยี่ห้อ',
            'brand_id.exists' => 'ไม่พบยี่ห้อที่เลือก',
        ]);

        if (! $currentBrand->switchTo((int) $data['brand_id'])) {
            abort(403, 'บัญชีนี้ไม่มีสิทธิ์ดูข้อมูลของยี่ห้อที่เลือก');
        }

        return back()->with('status', 'สลับมาดูข้อมูลของ '.Brand::find($data['brand_id'])->name.' แล้ว');
    }

    /** กลับไปใช้ยี่ห้อประจำตัว */
    public function destroy(CurrentBrand $currentBrand): RedirectResponse
    {
        $currentBrand->reset();

        return back()->with('status', 'กลับมาใช้ยี่ห้อประจำตัวแล้ว');
    }
}
