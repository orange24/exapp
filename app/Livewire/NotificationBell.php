<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * กระดิ่งใน header — generic ตั้งแต่แรก
 * ใครจะเอาไปแจ้งเรื่องอื่นทีหลัง (สต็อกต่ำ, เรตผิดปกติ, กะไม่ปิด)
 * แค่เขียน Notification class ใหม่ ไฟล์นี้ไม่ต้องแก้
 */
class NotificationBell extends Component
{
    public function getUnreadCountProperty(): int
    {
        $user = Auth::user();

        return $user ? $user->unreadNotifications()->count() : 0;
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
