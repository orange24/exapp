<?php

namespace App\Notifications;

use App\Models\SanctionScreening;
use Illuminate\Notifications\Notification;

class SanctionApprovedDespiteMatch extends Notification
{
    public function __construct(
        private readonly SanctionScreening $screening,
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
            'severity' => 'approved',
            'title' => 'อนุมัติทำรายการต่อ ทั้งที่พบชื่อใกล้เคียง',
            'screening_id' => $this->screening->id,
            'transaction_id' => $this->screening->transaction_id,
            'branch_name' => $this->screening->branch?->branch_name,
            'customer_name' => $this->screening->input_name,
            'approved_by' => $this->screening->decidedBy?->name,
            'reason' => $this->screening->decision_reason,
            'score' => (float) $this->screening->top_score,
            'link_route' => 'sanctions.review',
            'link_params' => ['screening' => $this->screening->id],
        ];
    }
}
