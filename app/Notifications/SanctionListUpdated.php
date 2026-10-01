<?php

namespace App\Notifications;

use App\Models\SanctionSyncRun;
use Illuminate\Notifications\Notification;

class SanctionListUpdated extends Notification
{
    public function __construct(
        private readonly SanctionSyncRun $run,
        private readonly int $newHits = 0,
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
            'severity' => 'info',
            'title' => sprintf(
                'รายชื่อ ปปง. อัปเดต: เพิ่ม %d ถอน %d',
                $this->run->entries_added,
                $this->run->entries_removed
            ),
            'sync_run_id' => $this->run->id,
            'list_code' => $this->run->list_code,
            'source_as_of' => $this->run->source_as_of?->format('Y-m-d'),
            'new_hits' => $this->newHits,
            'link_route' => 'reports.sanction-list-delta',
            'link_params' => [],
        ];
    }
}
