<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\UserSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public string $search = '';
    public string $terminateReason = '';
    public ?int $confirmTerminateId = null;

    #[Computed]
    public function activeSessions()
    {
        return UserSession::with('user.branch', 'user.role')
            ->where('is_terminated', false)
            ->when($this->search, function ($q) {
                $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                );
            })
            ->orderByDesc('last_activity')
            ->get();
    }

    public function askTerminate(int $sessionId): void
    {
        $this->confirmTerminateId = $sessionId;
        $this->terminateReason = '';
    }

    public function cancelTerminate(): void
    {
        $this->confirmTerminateId = null;
        $this->terminateReason = '';
    }

    public function terminate(): void
    {
        if (! $this->confirmTerminateId) return;

        $session = UserSession::find($this->confirmTerminateId);
        if (! $session) return;

        // Mark as terminated in DB
        $session->update([
            'is_terminated'     => true,
            'terminated_by'     => Auth::id(),
            'terminated_at'     => now(),
            'terminated_reason' => $this->terminateReason ?: 'ยกเลิกโดย Admin',
        ]);

        // Actually destroy the PHP session
        // Store a flag in cache so the user's next request fails auth
        Cache::put('session_terminated_' . $session->session_id, true, now()->addHours(24));

        $this->confirmTerminateId = null;
        $this->terminateReason = '';

        $this->dispatch('session-terminated');
    }

    public function terminateAllForUser(int $userId): void
    {
        $sessions = UserSession::where('user_id', $userId)
            ->where('is_terminated', false)
            ->get();

        foreach ($sessions as $session) {
            $session->update([
                'is_terminated'     => true,
                'terminated_by'     => Auth::id(),
                'terminated_at'     => now(),
                'terminated_reason' => 'ยกเลิกทุก session โดย Admin',
            ]);
            Cache::put('session_terminated_' . $session->session_id, true, now()->addHours(24));
        }
    }

    public function render()
    {
        return view('livewire.admin.session-manager');
    }
};
?>

{{-- Template placeholder --}}