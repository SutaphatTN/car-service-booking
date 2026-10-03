<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** รายชื่อช่าง (เฉพาะ admin) — แยกตามยี่ห้อที่กำลังทำงานอยู่ */
class TechnicianController extends Controller
{
    public function index(): View
    {
        return view('technicians.index', [
            'technicians' => Technician::ordered()
                ->withCount(['jobs', 'jobs as month_jobs_count' => fn ($q) => $q->where('work_date', '>=', now()->startOfMonth()->toDateString())])
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $technician = Technician::create($this->validated($request));

        return back()->with('status', 'เพิ่มช่าง '.$technician->name.' แล้ว');
    }

    public function update(Request $request, Technician $technician): RedirectResponse
    {
        $technician->update($this->validated($request, $technician));

        return back()->with('status', 'แก้ไขชื่อช่างเป็น '.$technician->name.' แล้ว');
    }

    /** ลบแบบ soft delete — งานย้อนหลังยังเห็นชื่อ แต่เลือกลงงานใหม่ไม่ได้แล้ว */
    public function destroy(Technician $technician): RedirectResponse
    {
        $technician->delete();

        return back()->with('status', 'ลบช่าง '.$technician->name.' แล้ว (งานที่เคยลงไว้ยังอยู่ในปฏิทิน)');
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Technician $technician = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'name' => [
                'required', 'max:100',
                // ชื่อซ้ำในยี่ห้อเดียวกันไม่ได้ เลือกในฟอร์มลงงานจะแยกไม่ออก (ไม่นับคนที่ลบไปแล้ว)
                Rule::unique('technicians', 'name')
                    ->where('brand_id', current_brand_id())
                    ->whereNull('deleted_at')
                    ->ignore($technician?->id),
            ],
        ], [
            'name.required' => 'กรุณากรอกชื่อช่าง',
            'name.max' => 'ชื่อยาวเกิน 100 ตัวอักษร',
            'name.unique' => 'มีช่างชื่อนี้อยู่แล้ว',
        ]);
    }
}
