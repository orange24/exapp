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
            // decide() ถูกเรียกกับทุกผลการตัดสิน ไม่ใช่แค่การอนุมัติผ่าน
            // ถ้าใช้หัวข้อเดียวตายตัว เคสที่ยืนยันว่า "เป็นบุคคลเดียวกันจริง"
            // จะขึ้นในกระดิ่งว่า "อนุมัติทำรายการต่อ" ซึ่งความหมายตรงข้ามกันคนละขั้ว
            'severity' => match ($this->screening->decision) {
                SanctionScreening::DECISION_TRUE_MATCH => 'blocked',
                SanctionScreening::DECISION_ESCALATED => 'info',
                default => 'approved',
            },
            'decision' => $this->screening->decision,
            'title' => match ($this->screening->decision) {
                SanctionScreening::DECISION_TRUE_MATCH => 'ยืนยันว่าเป็นบุคคลเดียวกัน — ต้องระงับและรายงาน ปปง.',
                SanctionScreening::DECISION_ESCALATED => 'ส่งต่อให้ผู้บริหารตัดสิน — ยังไม่ได้ข้อยุติ',
                default => 'อนุมัติทำรายการต่อ ทั้งที่พบชื่อใกล้เคียง',
            },
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
