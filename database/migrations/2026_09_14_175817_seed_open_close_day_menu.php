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
        $parent = Menu::where('key', 'inventory')->first();
        if (! $parent) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'inventory.open-close-day'],
            [
                'label_th' => 'เปิด/ปิดวันทำการ',
                'label_en' => 'Open/Close Day',
                'route' => 'inventory.open-close-day',
                'icon' => 'lock-closed',
                'parent_id' => $parent->id,
                'order' => 5,
            ]
        );

        $roles = Role::whereIn('name', ['staff', 'branch_manager'])->get();
        foreach ($roles as $role) {
            if (! $role->menus()->where('menu_id', $menu->id)->exists()) {
                $role->menus()->attach($menu->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menu = Menu::where('key', 'inventory.open-close-day')->first();
        if ($menu) {
            $menu->roles()->detach();
            $menu->delete();
        }
    }
};
