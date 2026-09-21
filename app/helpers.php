<?php

use App\Models\Brand;
use App\Support\CurrentBrand;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('thai_date')) {
    /** วันที่แบบไทย เช่น "พฤหัสบดีที่ 4 กันยายน 2569" */
    function thai_date(CarbonInterface|string $date, bool $short = false): string
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $monthsShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        $year = $date->year + 543;

        if ($short) {
            return $date->day.' '.$monthsShort[$date->month].' '.substr((string) $year, -2);
        }

        return $days[$date->dayOfWeek].'ที่ '.$date->day.' '.$months[$date->month].' '.$year;
    }
}

if (! function_exists('thai_month')) {
    /** เดือนและปีแบบไทย เช่น "กันยายน 2569" */
    function thai_month(CarbonInterface|string $date): string
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

        return $months[$date->month].' '.($date->year + 543);
    }
}

if (! function_exists('baht')) {
    /** จัดรูปแบบจำนวนเงิน/ตัวเลขให้มีลูกน้ำ */
    function baht(int|float $amount): string
    {
        return number_format($amount);
    }
}

if (! function_exists('phone_format')) {
    /** เบอร์โทรในฐานข้อมูลเก็บเป็นตัวเลขล้วน แสดงผลเป็น 088-4535-354 */
    function phone_format(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) !== 10) {
            return (string) $phone;
        }

        return substr($digits, 0, 3).'-'.substr($digits, 3, 4).'-'.substr($digits, 7);
    }
}

if (! function_exists('current_brand')) {
    /** ยี่ห้อรถที่กำลังทำงานอยู่ในคำขอนี้ — null เมื่อยังไม่ได้เข้าสู่ระบบ */
    function current_brand(): ?Brand
    {
        return app(CurrentBrand::class)->get();
    }
}

if (! function_exists('current_brand_id')) {
    /** id ของยี่ห้อที่กำลังทำงานอยู่ — global scope ของ Appointment ใช้ค่านี้กรองข้อมูล */
    function current_brand_id(): ?int
    {
        return app(CurrentBrand::class)->id();
    }
}

if (! function_exists('asset_version')) {
    /**
     * ลิงก์ไฟล์ static พร้อมเลขเวอร์ชันจากเวลาที่ไฟล์ถูกแก้ครั้งล่าสุด
     *
     * โปรเจคนี้ไม่มีขั้นตอน build (ไม่ได้ใช้ Vite/Mix) ไฟล์จึงมี URL คงที่ตลอด
     * แก้ CSS แล้วอัปขึ้นเซิร์ฟเวอร์ เบราว์เซอร์ของผู้ใช้จะยังหยิบไฟล์เก่าที่แคชไว้มาใช้
     * จนกว่าจะกด Ctrl+F5 ซึ่งพนักงานทั่วไปไม่รู้ หน้าเว็บก็จะเพี้ยนแบบหาสาเหตุไม่เจอ
     *
     * ต่อเวลาแก้ไขท้าย URL ทำให้ URL เปลี่ยนเองทุกครั้งที่ไฟล์เปลี่ยน ได้ผลเหมือน
     * cache busting ของ Vite โดยไม่ต้องมี Node
     */
    function asset_version(string $path): string
    {
        $file = public_path($path);

        // ไฟล์หายไปก็ยังต้องคืนลิงก์ตามปกติ ปล่อยให้เบราว์เซอร์ฟ้อง 404 เอง ดีกว่าหน้าเว็บพัง
        $stamp = is_file($file) ? filemtime($file) : false;

        return $stamp === false ? asset($path) : asset($path).'?v='.$stamp;
    }
}
