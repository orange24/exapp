<?php

namespace App\Http\Controllers\Sanction;

use App\Http\Controllers\Controller;
use App\Models\SanctionEntry;
use App\Models\SanctionSyncRun;
use App\Services\Sanction\SyncTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function __invoke(Request $request, SyncTrigger $trigger)
    {
        // ?action=update สั่งเริ่มอัปเดต แล้ว redirect กลับมาหน้าเดิมแบบไม่มี query
        //
        // ที่ต้อง redirect ไม่ใช่ render ตรง ๆ เพราะ URL นี้เปลี่ยนสถานะระบบ
        // ถ้าปล่อยให้ค้างอยู่บน address bar คนกด refresh หรือ back จะสั่งซ้ำ
        // โดยไม่รู้ตัว แล้วไปยิงเซิร์ฟเวอร์ ปปง. เพิ่มอีกรอบ
        if ($request->query('action') === 'update') {
            $result = $trigger->start((int) Auth::id());

            return redirect()
                ->route('sanctions.sync')
                ->with($result['ok'] ? 'success' : 'error', $result['message']);
        }

        return view('sanction.sync', [
            'inProgress' => $trigger->inProgress(),
            'runs' => SanctionSyncRun::with('forcedBy')
                ->orderByDesc('started_at')
                ->limit(15)
                ->get(),
            'activeEntries' => SanctionEntry::query()->active()->count(),
        ]);
    }
}
