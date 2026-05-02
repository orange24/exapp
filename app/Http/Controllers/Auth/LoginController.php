<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLogin(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Rate limiting: max 5 attempts per minute per IP
        $key = 'login.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "คุณพยายาม login มากเกินไป กรุณารอ {$seconds} วินาที",
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'อีเมล หรือ รหัสผ่านไม่ถูกต้อง',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Record session with device info
        $this->recordSession($request);

        // Set default working counter from user's branch
        $user = Auth::user();
        if ($user->branch_id) {
            $defaultCounter = \App\Models\Counter::where('branch_id', $user->branch_id)
                ->where('is_active', true)->first();
            if ($defaultCounter) {
                $request->session()->put('working_counter_id', $defaultCounter->id);
                $request->session()->put('working_counter_name', $defaultCounter->counter_name);
            }
        }

        return redirect()->intended(route('dashboard'));
    }

    protected function recordSession(Request $request): void
    {
        $userAgent = $request->userAgent() ?? '';
        [$browser, $os, $device] = $this->parseUserAgent($userAgent);

        UserSession::create([
            'user_id'       => Auth::id(),
            'session_id'    => $request->session()->getId(),
            'ip_address'    => $request->ip(),
            'user_agent'    => $userAgent,
            'browser'       => $browser,
            'os'            => $os,
            'device_type'   => $device,
            'last_activity' => now(),
        ]);
    }

    protected function parseUserAgent(string $ua): array
    {
        // Browser detection
        $browser = 'Unknown';
        if (str_contains($ua, 'Edg/'))        $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/'))    $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome'))  $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari'))  $browser = 'Safari';

        // OS detection
        $os = 'Unknown';
        if (str_contains($ua, 'Windows'))     $os = 'Windows';
        elseif (str_contains($ua, 'Mac OS'))  $os = 'macOS';
        elseif (str_contains($ua, 'Android')) $os = 'Android';
        elseif (str_contains($ua, 'iPhone'))  $os = 'iOS';
        elseif (str_contains($ua, 'Linux'))   $os = 'Linux';

        // Device type
        $device = 'desktop';
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone'))  $device = 'mobile';
        elseif (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) $device = 'tablet';

        return [$browser, $os, $device];
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Mark the current session as terminated (self-logout)
        UserSession::where('session_id', $request->session()->getId())
            ->update(['is_terminated' => true, 'terminated_at' => now(), 'terminated_reason' => 'Logout']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('login'));
    }
}
