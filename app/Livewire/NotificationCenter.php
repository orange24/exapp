<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationCenter extends Component
{
    use WithPagination;

    /** all | unread | sanction | system */
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function markAllAsRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    public function markAsRead(string $id): void
    {
        Auth::user()?->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function getNotificationsProperty()
    {
        $query = Auth::user()->notifications()->getQuery();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($this->filter === 'sanction') {
            $query->where('data', 'like', '%"category":"sanction"%');
        } elseif ($this->filter === 'system') {
            $query->where('data', 'not like', '%"category":"sanction"%');
        }

        return $query->latest()->paginate(20);
    }

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.notification-center');
    }
}
