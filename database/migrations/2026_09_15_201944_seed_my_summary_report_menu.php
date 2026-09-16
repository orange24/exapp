<?php

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parent = Menu::firstOrCreate(
            ['key' => 'reports'],
            [
                'label_th' => 'รายงาน',
                'label_en' => 'Reports',
                'route' => null,
                'icon' => 'document-chart-bar',
                'parent_id' => null,
                'order' => 4,
            ]
        );

        $menu = Menu::firstOrCreate(
            ['key' => 'reports.my-summary'],
            [
                'label_th' => 'สรุปยอดของฉัน',
                'label_en' => 'Summary Report',
                'route' => 'reports.my-summary',
                'icon' => 'document-arrow-down',
                'parent_id' => $parent->id,
                'order' => 3,
            ]
        );

        $staff = Role::where('name', 'staff')->first();
        if (! $staff) {
            return;
        }

        foreach ([$parent, $menu] as $m) {
            if (! $staff->menus()->where('menu_id', $m->id)->exists()) {
                $staff->menus()->attach($m->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menu = Menu::where('key', 'reports.my-summary')->first();
        if ($menu) {
            $menu->roles()->detach();
            $menu->delete();
        }
    }
};
