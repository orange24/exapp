<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ตั้งเวลาตัดรอบของสาขาจากหน้าจัดการสาขา
 */
class BranchCutOffSettingTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'branch_code' => $this->branch->branch_code,
            'branch_name' => $this->branch->branch_name,
            'type' => 'branch',
            'is_active' => '1',
        ], $overrides);
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->superAdminUser)
            ->put(route('admin.branches.update', $this->branch), $this->payload($overrides));
    }

    public function test_an_admin_can_set_the_cut_off_of_a_branch(): void
    {
        $this->save(['cut_off_time' => '02:30'])->assertRedirect();

        $this->assertSame('02:30:00', substr((string) $this->branch->fresh()->cut_off_time, 0, 8));
    }

    public function test_leaving_it_blank_puts_the_branch_back_on_the_central_value(): void
    {
        $this->branch->update(['cut_off_time' => '02:00:00']);

        $this->save(['cut_off_time' => ''])->assertRedirect();

        $this->assertNull($this->branch->fresh()->cut_off_time);
    }

    public function test_an_afternoon_cut_off_is_refused(): void
    {
        // สูตรนิยามว่าวันทำการ D เริ่มที่เวลาตัดรอบของวันที่ D ถ้าตั้งเป็นสี่ทุ่ม
        // ความหมายจะกลับหัว — รายการตอนบ่ายของวันที่ 11 จะไปอยู่วันทำการที่ 10
        // จึงรับได้แต่ช่วงเช้ามืด
        $this->save(['cut_off_time' => '22:00'])->assertSessionHasErrors('cut_off_time');

        $this->assertNull($this->branch->fresh()->cut_off_time);
    }

    public function test_the_boundary_of_noon_is_refused_too(): void
    {
        $this->save(['cut_off_time' => '12:00'])->assertSessionHasErrors('cut_off_time');
    }

    public function test_the_last_accepted_value_before_noon_goes_through(): void
    {
        $this->save(['cut_off_time' => '11:59'])->assertRedirect();

        $this->assertSame('11:59:00', substr((string) $this->branch->fresh()->cut_off_time, 0, 8));
    }

    public function test_midnight_is_accepted_and_means_the_calendar_day(): void
    {
        $this->save(['cut_off_time' => '00:00'])->assertRedirect();

        $this->assertSame('00:00:00', substr((string) $this->branch->fresh()->cut_off_time, 0, 8));
    }

    public function test_a_new_branch_can_be_created_with_its_own_cut_off(): void
    {
        $this->actingAs($this->superAdminUser)
            ->post(route('admin.branches.store'), [
                'branch_code' => 'NEWB',
                'branch_name' => 'สาขาใหม่',
                'type' => 'branch',
                'is_active' => '1',
                'cut_off_time' => '04:00',
            ])
            ->assertRedirect();

        $this->assertSame('04:00:00', substr(
            (string) Branch::where('branch_code', 'NEWB')->firstOrFail()->cut_off_time, 0, 8
        ));
    }

    public function test_the_form_shows_the_current_value(): void
    {
        $this->branch->update(['cut_off_time' => '02:30:00']);

        $this->actingAs($this->superAdminUser)
            ->get(route('admin.branches.edit', $this->branch))
            ->assertOk()
            ->assertSee('name="cut_off_time"', false)
            ->assertSee('value="02:30"', false);
    }
}
