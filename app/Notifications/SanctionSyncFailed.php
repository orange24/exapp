<?php

namespace App\Notifications;

use App\Models\SanctionSyncRun;
use Illuminate\Notifications\Notification;

class SanctionSyncFailed extends Notification
{
    public function __construct(
        private readonly SanctionSyncRun $run,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'sanction',
            'severity' => 'error',
            'title' => $this->run->status === SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK
                ? 'Sync รายชื่อถูกยกเลิก — หยุดไว้เพื่อความปลอดภัย'
                : 'Sync รายชื่อล้มเหลว',
            'sync_run_id' => $this->run->id,
            'list_code' => $this->run->list_code,
            'status' => $this->run->status,
            'error_message' => $this->run->error_message,
            'link_route' => 'reports.sanction-sync-health',
            'link_params' => [],
        ];
    }
}
