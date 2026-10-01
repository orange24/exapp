<?php

use App\Models\Menu;
use Database\Seeders\SanctionMenuSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new SanctionMenuSeeder())->seedReviewMenu();
    }

    public function down(): void
    {
        foreach (['sanctions.review', 'sanctions'] as $key) {
            $menu = Menu::where('key', $key)->first();

            if ($menu) {
                $menu->roles()->detach();
                $menu->delete();
            }
        }
    }
};
