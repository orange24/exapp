<?php

namespace App\Services\Sanction;

use App\Models\SanctionScreening;
use App\Models\SanctionSyncRun;
use App\Models\User;
use App\Notifications\SanctionApprovedDespiteMatch;
use App\Notifications\SanctionListUpdated;
use App\Notifications\SanctionSyncFailed;
use App\Notifications\SanctionTransactionBlocked;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SanctionNotifier
{
    public function transactionBlocked(SanctionScreening $screening): void
    {
        Notification::send(
            $this->adminsAnd($screening->branch_id),
            new SanctionTransactionBlocked($screening)
        );
    }

    public function approvedDespiteMatch(SanctionScreening $screening): void
    {
        Notification::send(
            $this->adminsAnd($screening->branch_id),
            new SanctionApprovedDespiteMatch($screening)
        );
    }

    /** เรื่องระบบ — ส่งหา admin อย่างเดียว ผู้จัดการสาขาแก้อะไรไม่ได้อยู่แล้ว */
    public function syncFailed(SanctionSyncRun $run): void
    {
        Notification::send($this->admins(), new SanctionSyncFailed($run));
    }

    public function listUpdated(SanctionSyncRun $run, int $newHits = 0): void
    {
        Notification::send($this->admins(), new SanctionListUpdated($run, $newHits));
    }

    /** @return Collection<int, User> */
    private function admins(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))->get();
    }

    /**
     * admin ทุกคน + branch_manager ของสาขาที่เกิดเหตุ
     *
     * หาสมาชิกสาขาผ่าน user_branches (belongsToMany) ไม่ใช่คอลัมน์ users.branch_id
     * เพราะผู้ใช้ผูกได้หลายสาขา และการอ่าน branch_id ตรงๆ เป็นบั๊กซ้ำซากของโปรเจกต์นี้
     *
     * @return Collection<int, User>
     */
    private function adminsAnd(?int $branchId): Collection
    {
        $recipients = $this->admins();

        if ($branchId !== null) {
            $managers = User::whereHas('role', fn ($q) => $q->where('name', 'branch_manager'))
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
                ->get();

            $recipients = $recipients->concat($managers);
        }

        return $recipients->unique('id')->values();
    }
}
