<?php

namespace App\Http\Middleware;

use App\Models\CardReaderDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ยืนยันตัวเครื่องอ่านบัตรด้วย token ประจำเครื่อง
 *
 * ไม่มี session ไม่มี cookie — agent เป็นโปรแกรมที่รันอยู่ที่สาขา ไม่ใช่คน
 * เครื่องที่ถูกเพิกถอนได้ 401 ทันที agent เห็นแล้วหยุดทำงานถาวร
 */
class AuthenticateCardReader
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'ไม่มี token'], 401);
        }

        // ค้นด้วย hash เสมอ ไม่เคยเทียบ token ตัวจริงกับอะไรในฐานข้อมูล
        $device = CardReaderDevice::query()
            ->active()
            ->where('token_hash', CardReaderDevice::hashToken($token))
            ->first();

        if ($device === null) {
            return response()->json(['message' => 'token ไม่ถูกต้องหรือถูกเพิกถอนแล้ว'], 401);
        }

        $request->attributes->set('card_reader_device', $device);

        return $next($request);
    }
}
