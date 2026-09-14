<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * กันไม่ให้เข้าหน้าที่ต้องใช้ข้อมูลรายยี่ห้อ ถ้าบัญชีนั้นยังไม่มียี่ห้อที่ใช้งานได้
 * (เช่นยี่ห้อประจำตัวถูกตั้ง is_active = 0 ไว้ในฐานข้อมูล)
 */
class EnsureBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && current_brand() === null) {
            abort(403, 'บัญชีนี้ยังไม่ได้ผูกกับยี่ห้อรถที่เปิดใช้งานอยู่ กรุณาติดต่อผู้ดูแลระบบ');
        }

        return $next($request);
    }
}
