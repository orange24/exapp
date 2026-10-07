<?php

namespace App\Services\Sanction;

use App\Models\SanctionSyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * สั่ง sync รายชื่อ ปปง. จากหน้าเว็บ
 *
 * ทำไมไม่รัน sync ในคำขอ HTTP ตรง ๆ: config หน่วงคำขอไป ปปง. ไว้ 1 วินาที
 * ต่อหน้าเป็นมารยาทกับเซิร์ฟเวอร์ราชการ วันที่รายชื่อเปลี่ยนต้องไล่อ่านหน้า
 * รายละเอียดกว่าพันหน้า = อย่างน้อย 17 นาที ขณะที่เว็บมี timeout 5 นาที
 * คำขอจะถูกฆ่ากลางทางโดยที่ sync เพิ่งไปได้หนึ่งในสาม
 *
 * บน Cloud Run จึงสั่ง Cloud Run Job (timeout 1 ชั่วโมง) แล้วให้หน้าเว็บ
 * ไปอ่านความคืบหน้าจากตาราง sanction_sync_runs แทน
 */
class SyncTrigger
{
    /**
     * ถือว่ารอบที่ค้างเกินเวลานี้ตายไปแล้ว ไม่ใช่กำลังทำงาน
     *
     * ตั้งเท่า timeout ของ Job พอดี — ถ้าสั้นกว่านั้นเราจะปล่อยให้สั่งรอบใหม่
     * ทับรอบที่ยังวิ่งอยู่จริง กลายเป็นยิง ปปง. สองทางพร้อมกัน
     */
    public const STALE_AFTER_MINUTES = 60;

    /** รอบที่กำลังทำงานอยู่ ถ้ามี */
    public function inProgress(): ?SanctionSyncRun
    {
        return SanctionSyncRun::query()
            ->whereNull('finished_at')
            ->where('started_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function start(int $userId): array
    {
        if ($running = $this->inProgress()) {
            return [
                'ok' => false,
                'message' => 'มีการอัปเดตที่กำลังทำงานอยู่ (เริ่ม '
                    . $running->started_at->format('H:i:s')
                    . ') — รอให้รอบนี้จบก่อน จะได้ไม่ยิงเซิร์ฟเวอร์ ปปง. ซ้อนกันสองทาง',
            ];
        }

        return $this->onCloudRun()
            ? $this->startCloudRunJob($userId)
            : $this->startInline($userId);
    }

    /** Cloud Run ตั้ง K_SERVICE ให้ทุก container ของตัวเอง */
    private function onCloudRun(): bool
    {
        return env('K_SERVICE') !== null;
    }

    /**
     * เครื่อง dev ไม่มี Cloud Run Job ให้สั่ง — รันตรงนี้เลย
     *
     * บล็อกคำขอไว้จนเสร็จ ซึ่งยอมรับได้เฉพาะตอน dev ที่คนรันเห็นหน้าจอตัวเองอยู่
     */
    private function startInline(int $userId): array
    {
        Artisan::call('sanctions:sync', ['--list' => 'all', '--forced-by' => $userId]);

        return ['ok' => true, 'message' => 'อัปเดตรายชื่อเสร็จแล้ว (รันในเครื่อง dev)'];
    }

    private function startCloudRunJob(int $userId): array
    {
        $project = (string) config('sanction.trigger.project');
        $region = (string) config('sanction.trigger.region');
        $job = (string) config('sanction.trigger.job');

        if ($project === '' || $region === '' || $job === '') {
            return [
                'ok' => false,
                'message' => 'ยังไม่ได้ตั้งค่าปลายทางของ Cloud Run Job — แจ้งทีมผู้ดูแลระบบ',
            ];
        }

        $token = $this->metadataToken();

        if ($token === null) {
            return ['ok' => false, 'message' => 'ขอ token จาก metadata server ไม่สำเร็จ'];
        }

        $url = "https://run.googleapis.com/v2/projects/{$project}/locations/{$region}/jobs/{$job}:run";

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($url, [
                // ส่ง --forced-by เพิ่มเข้าไป เพื่อให้บันทึกรอบนี้รู้ว่าใครสั่ง
                // ไม่งั้นรอบที่คนกดเองจะดูเหมือนรอบที่ระบบรันเองทุกประการ
                'overrides' => [
                    'containerOverrides' => [[
                        'args' => ['php', 'artisan', 'sanctions:sync', '--list=all', "--forced-by={$userId}"],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            Log::error('สั่ง Cloud Run Job อัปเดตรายชื่อ ปปง. ไม่สำเร็จ', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'message' => 'สั่งงานไม่สำเร็จ (HTTP ' . $response->status() . ') — ดูรายละเอียดใน log',
            ];
        }

        return [
            'ok' => true,
            'message' => 'เริ่มอัปเดตรายชื่อแล้ว — ใช้เวลาไม่กี่นาทีถ้าไม่มีอะไรเปลี่ยน '
                . 'หรือราว 20 นาทีถ้า ปปง. แก้ประกาศ รีเฟรชหน้านี้เพื่อดูความคืบหน้า',
        ];
    }

    private function metadataToken(): ?string
    {
        try {
            $response = Http::withHeaders(['Metadata-Flavor' => 'Google'])
                ->timeout(5)
                ->get('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token');

            return $response->successful() ? ($response->json('access_token') ?: null) : null;
        } catch (\Throwable $e) {
            Log::error('อ่าน token จาก metadata server ไม่ได้: ' . $e->getMessage());

            return null;
        }
    }
}
