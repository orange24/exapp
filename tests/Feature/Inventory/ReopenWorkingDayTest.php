<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * เปิดวันทำการใหม่หลังกดปิดผิด
 *
 * ตอนกดปิดวันระบบยังไม่โอนสต็อกและยังไม่โพสต์ผลต่างเงินบาท (สองอย่างนั้นเกิดตอน
 * Admin อนุมัติ) การเปิดกลับจึงเป็นแค่การล้างยอดปิดที่กรอกค้างไว้ ตราบใดที่ยัง
 * ไม่อนุมัติ
 */
class ReopenWorkingDayTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 17500,
        ]);
    }

    /** เปิดวันแล้วปิดทันที เหมือนเคสกดผิดที่หน้าเคาน์เตอร์ */
    private function openThenClose(): WorkingDay
    {
        $today = now()->format('Y-m-d');

        Livewire::actingAs($this->staffUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $today)
            ->call('openDay')
            ->assertHasNoErrors();

        Livewire::actingAs($this->staffUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $today)
            ->call('prepareClosing')
            ->call('saveClosingAmounts')
            ->assertHasNoErrors();

        return WorkingDay::where('counter_id', $this->counter->id)->firstOrFail();
    }

    private function reopen(string $reason, ?string $date = null)
    {
        return Livewire::actingAs($this->staffUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $date ?? now()->format('Y-m-d'))
            ->call('reopenDay', $reason);
    }

    public function test_staff_can_reopen_a_day_closed_by_mistake(): void
    {
        $wd = $this->openThenClose();
        $this->assertEquals('closed', $wd->status);
        $this->assertEquals('pending', $wd->closing_status);

        $this->reopen('กดปิดผิด');

        $wd->refresh();
        $this->assertEquals('open', $wd->status);
        $this->assertNull($wd->closed_by);
        $this->assertNull($wd->closed_at);
        $this->assertNull($wd->closing_status);
        $this->assertNull($wd->closing_items);
        $this->assertNull($wd->closing_thb_expected);
        $this->assertNull($wd->closing_thb_actual);
        $this->assertNull($wd->closing_thb_variance);

        $this->assertEquals($this->staffUser->id, $wd->reopened_by);
        $this->assertNotNull($wd->reopened_at);
        $this->assertStringContainsString('กดปิดผิด', $wd->reopen_reason);
    }

    /** ห้ามเขียน opening_balance ทับ ไม่งั้นยอดยกมาจะกลายเป็นสต็อกกลางวัน */
    public function test_reopening_keeps_the_original_opening_balance(): void
    {
        $this->openThenClose();

        // ระหว่างวันสต็อกลดลงจาก 500 เหลือ 300
        CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)
            ->update(['quantity' => 300]);

        $this->reopen('กดปิดผิด');

        $inv = Inventory::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)
            ->whereDate('date', now()->format('Y-m-d'))
            ->firstOrFail();

        $this->assertEquals(500.0, (float) $inv->opening_balance);
    }

    public function test_rejected_day_can_be_reopened(): void
    {
        $wd = $this->openThenClose();
        $wd->update(['closing_status' => 'rejected', 'closing_notes' => 'นับไม่ตรง']);

        $this->reopen('กรอกยอดใหม่ตามที่ถูกปฏิเสธ');

        $this->assertEquals('open', $wd->fresh()->status);
        $this->assertNull($wd->fresh()->closing_notes);
    }

    public function test_approved_day_cannot_be_reopened(): void
    {
        $wd = $this->openThenClose();

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('approveClosing', $wd->id);

        $this->assertEquals('approved', $wd->fresh()->closing_status);

        $this->reopen('อยากเปิดใหม่');

        $this->assertEquals('closed', $wd->fresh()->status);
        $this->assertEquals('approved', $wd->fresh()->closing_status);
    }

    public function test_past_day_cannot_be_reopened(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');

        $wd = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => $yesterday,
            'opening_thb_cash' => 0,
            'status' => 'closed',
            'opened_by' => $this->staffUser->id,
            'opened_at' => now()->subDay(),
            'closed_by' => $this->staffUser->id,
            'closed_at' => now()->subDay(),
            'closing_status' => 'pending',
        ]);

        $this->reopen('ขอเปิดวันเก่า', $yesterday);

        $this->assertEquals('closed', $wd->fresh()->status);
    }

    public function test_reason_is_required(): void
    {
        $wd = $this->openThenClose();

        $this->reopen('   ');

        $this->assertEquals('closed', $wd->fresh()->status);
        $this->assertNull($wd->fresh()->reopened_at);
    }

    public function test_every_reopen_is_appended_to_the_log(): void
    {
        $this->openThenClose();
        $this->reopen('รอบแรก กดผิด');

        // ปิดอีกครั้งแล้วเปิดใหม่อีกรอบ
        Livewire::actingAs($this->staffUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('prepareClosing')
            ->call('saveClosingAmounts');

        $this->reopen('รอบสอง ลืมนับลิ้นชัก');

        $wd = WorkingDay::where('counter_id', $this->counter->id)->firstOrFail();
        $this->assertStringContainsString('รอบแรก กดผิด', $wd->reopen_reason);
        $this->assertStringContainsString('รอบสอง ลืมนับลิ้นชัก', $wd->reopen_reason);
        $this->assertCount(2, array_filter(explode("\n", trim($wd->reopen_reason))));
    }
}
