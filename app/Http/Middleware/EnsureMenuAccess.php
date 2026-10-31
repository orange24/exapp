<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * เปิดหน้าได้เฉพาะ role ที่ได้เมนูนั้น — ใช้: ->middleware('menu:admin.currencies')
 *
 * หน้าข้อมูลหลักเคยไม่มีอะไรกั้นเลย ซ่อนเมนูอย่างเดียว ใครพิมพ์ URL ก็แก้ได้
 * ผูกประตูกับเมนูทำให้ปรับสิทธิ์ที่หน้าเมนูแล้วประตูตามไปด้วยเสมอ
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        abort_unless($request->user()?->hasMenuAccess($menuKey), 403, 'คุณไม่มีสิทธิ์เข้าหน้านี้');

        return $next($request);
    }
}
