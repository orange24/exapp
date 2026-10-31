<?php

namespace App\Providers;

use App\Support\ReadOnlyGuard;
use App\Support\WorkingDayClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ตัวเดียวต่อคำขอ เพื่อให้ทุกจุดในหน้าเดียวกันเห็นเวลาตัดรอบค่าเดียว
        // และอ่านตารางตั้งค่าแค่ครั้งเดียว
        $this->app->singleton(WorkingDayClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // บัญชีอ่านอย่างเดียวเขียนฐานข้อมูลไม่ได้ — ดูเหตุผลที่ ReadOnlyGuard
        DB::beforeExecuting(fn (string $sql) => ReadOnlyGuard::check($sql));
    }
}
