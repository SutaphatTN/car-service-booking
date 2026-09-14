<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Support\Facades\Auth;

/**
 * ยี่ห้อที่ผู้ใช้ "กำลังทำงานอยู่" ในคำขอนี้
 *
 * ปกติคือยี่ห้อประจำตัว (users.brand_id) แต่ถ้ากดสลับยี่ห้อไว้
 * จะเก็บ id ไว้ใน session แล้วใช้ค่านั้นแทน — สลับได้เฉพาะยี่ห้อที่มีสิทธิ์
 * (ยี่ห้อประจำตัว + แถวในตาราง brand_user)
 *
 * ผูกเป็น scoped ไว้ที่ AppServiceProvider จึงมีตัวเดียวต่อหนึ่งคำขอ
 * เรียกใช้ผ่าน current_brand() / current_brand_id() ใน app/helpers.php
 */
class CurrentBrand
{
    public const SESSION_KEY = 'brand_switch';

    private ?int $id = null;

    private bool $resolved = false;

    private ?Brand $brand = null;

    /** id ของยี่ห้อที่ใช้งานอยู่ — null เมื่อยังไม่ได้เข้าสู่ระบบ */
    public function id(): ?int
    {
        if (! $this->resolved) {
            $this->id = $this->resolve();
            $this->resolved = true;
        }

        return $this->id;
    }

    public function get(): ?Brand
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return $this->brand ??= Brand::find($id);
    }

    /** กดสลับยี่ห้อค้างไว้อยู่หรือเปล่า (ใช้โชว์ป้าย "สลับอยู่" กับปุ่มกลับค่าเดิม) */
    public function isSwitched(): bool
    {
        $chosen = session(self::SESSION_KEY);

        return $chosen !== null
            && $this->id() === (int) $chosen
            && $this->id() !== Auth::user()?->brand_id;
    }

    /** จำยี่ห้อที่เลือกไว้ใน session — คืน false ถ้าผู้ใช้ไม่มีสิทธิ์ยี่ห้อนั้น */
    public function switchTo(int $brandId): bool
    {
        if (! Auth::user()?->canAccessBrand($brandId)) {
            return false;
        }

        session([self::SESSION_KEY => $brandId]);
        $this->forget();

        return true;
    }

    /** กลับไปใช้ยี่ห้อประจำตัว */
    public function reset(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->forget();
    }

    private function resolve(): ?int
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        // นับเฉพาะยี่ห้อที่ยังเปิดใช้งาน — บัญชีที่ไม่เหลือยี่ห้อเลยจะถูก EnsureBrand กันไว้
        $accessible = $user->accessibleBrandIds();

        if ($accessible === []) {
            return null;
        }

        $chosen = session(self::SESSION_KEY);

        // ยี่ห้อใน session อาจถูกถอนสิทธิ์หรือปิดใช้งานไปแล้วหลังกดสลับ จึงต้องตรวจซ้ำทุกคำขอ
        if ($chosen !== null && in_array((int) $chosen, $accessible, true)) {
            return (int) $chosen;
        }

        // ยี่ห้อประจำตัวถูกปิดใช้งาน แต่ยังมียี่ห้ออื่นอยู่ ก็ให้ทำงานต่อด้วยยี่ห้อนั้นแทน
        return in_array($user->brand_id, $accessible, true)
            ? $user->brand_id
            : $accessible[0];
    }

    private function forget(): void
    {
        $this->resolved = false;
        $this->brand = null;
    }
}
