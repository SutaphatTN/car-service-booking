<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** ใช้แบบ ->middleware('role:sa') หรือ 'role:sa,admin' */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ($roles !== [] && ! $user->hasRole(...$roles))) {
            abort(403, 'บัญชีนี้ไม่มีสิทธิ์ใช้งานส่วนนี้');
        }

        return $next($request);
    }
}
