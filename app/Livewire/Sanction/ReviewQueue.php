<?php

namespace App\Livewire\Sanction;

use App\Models\Branch;
use App\Models\SanctionScreening;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReviewQueue extends Component
{
    /** @var array<int, int> */
    public array $selected = [];

    public string $bulkReason = '';

    public ?int $branchFilter = null;
    public ?string $listFilter = null;

    /**
     * nullable โดยตั้งใจ — Livewire แปลงช่อง number ที่ถูกลบว่างเป็น null
     * ถ้าประกาศเป็น int เฉย ๆ การลบตัวเลขออกจะโยน TypeError ทันที
     */
    public ?int $minScore = 0;

    public function updatedBranchFilter(): void
    {
        $this->selected = [];
    }

    public function updatedListFilter(): void
    {
        $this->selected = [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRowsProperty(): array
    {
        $query = SanctionScreening::awaitingDecision()
            ->with(['matches.entry', 'customer', 'branch', 'screenedBy'])
            ->where('top_score', '>=', $this->minScore ?? 0);

        if ($this->branchFilter !== null) {
            $query->where('branch_id', $this->branchFilter);
        }

        $rows = $query->orderByDesc('top_score')->limit(200)->get();

        // ตัว <select> ส่งค่า "" กลับมาเมื่อเลือก "ทั้งหมด" ไม่ได้ส่ง null
        // เช็ค !== null เพียว ๆ จะกรองด้วยสตริงว่างแล้วคิวหายทั้งหน้า
        if (filled($this->listFilter)) {
            $rows = $rows->filter(
                fn (SanctionScreening $s): bool => $s->matches
                    ->contains(fn ($m) => $m->entry?->list_code === $this->listFilter)
            );
        }

        return $rows->map(fn (SanctionScreening $s): array => [
            'id' => $s->id,
            'top_score' => (float) $s->top_score,
            'input_name' => $s->input_name,
            'input_dob' => $s->input_dob,
            'input_nationality' => $s->input_nationality,
            'input_id_number' => $s->input_id_number,
            'branch_name' => $s->branch?->branch_name,
            'trigger' => $s->trigger,
            'screened_at' => $s->screened_at?->format('d/m/Y H:i'),
            'transaction_id' => $s->transaction_id,
            'matches' => $s->matches->map(fn ($m): array => [
                'list_code' => $m->entry?->list_code,
                'name' => $m->entry?->name_en ?: $m->entry?->name_th,
                'dob' => $m->entry?->date_of_birth,
                'nationality' => $m->entry?->nationality,
                'matched_on' => $m->matched_on,
                'score' => (float) $m->score,
            ])->all(),
        ])->values()->all();
    }

    public function getBranchesProperty()
    {
        return Branch::orderBy('branch_name')->get(['id', 'branch_name']);
    }

    /**
     * ตัดสินเป็นชุด — จำเป็นเพราะ re-scan ครั้งแรกจะเจอ false positive เป็นร้อย
     * ถ้าต้องกดทีละใบ admin จะไม่มีวันเคลียร์หมด
     */
    public function bulkDecide(string $decision): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->hasPermission('module7', 'approve'), 403);

        $this->resetErrorBag();

        if (mb_strlen(trim($this->bulkReason)) < SanctionScreeningService::MIN_REASON_LENGTH) {
            $this->addError('bulkReason', 'ต้องระบุเหตุผลอย่างน้อย '
                . SanctionScreeningService::MIN_REASON_LENGTH . ' ตัวอักษร');

            return;
        }

        if ($this->selected === []) {
            $this->addError('bulkReason', 'ยังไม่ได้เลือกรายการ');

            return;
        }

        $service = app(SanctionScreeningService::class);

        foreach (SanctionScreening::whereIn('id', $this->selected)->get() as $screening) {
            // decide() โยน exception ถ้ารายการถูกตัดสินไปแล้ว — คนอื่นอาจเพิ่งเคลียร์
            // รายการเดียวกันไปในอีกแท็บ ข้ามไปเฉย ๆ ไม่ใช่ล้มทั้งชุด
            if (! $screening->needsDecision()) {
                continue;
            }

            $service->decide(
                screening: $screening,
                decision: $decision,
                decidedBy: $user->id,
                reason: trim($this->bulkReason),
            );
        }

        $this->selected = [];
        $this->bulkReason = '';

        session()->flash('message', 'บันทึกการตัดสินใจเรียบร้อย');
    }

    public function render()
    {
        return view('livewire.sanction.review-queue');
    }
}
