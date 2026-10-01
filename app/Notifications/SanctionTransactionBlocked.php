<?php

namespace App\Notifications;

use App\Models\SanctionScreening;
use Illuminate\Notifications\Notification;

class SanctionTransactionBlocked extends Notification
{
    public function __construct(
        private readonly SanctionScreening $screening,
    ) {
    }

    /** database channel เท่านั้น — production ไม่มี queue worker */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $entry = $this->screening->matches()->with('entry')->first()?->entry;

        return [
            'category' => 'sanction',
            'severity' => 'blocked',
            'title' => 'ธุรกรรมถูกระงับ — พบรายชื่อตรงกัน',
            'screening_id' => $this->screening->id,
            'transaction_id' => $this->screening->transaction_id,
            'branch_name' => $this->screening->branch?->branch_name,
            'customer_name' => $this->screening->input_name,
            'matched_list' => $entry?->list_code,
            'matched_name' => $entry?->name_en ?: $entry?->name_th,
            'link_route' => 'sanctions.review',
            'link_params' => ['screening' => $this->screening->id],
        ];
    }
}
