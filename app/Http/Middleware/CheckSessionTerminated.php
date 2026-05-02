<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionTerminated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $sessionId = $request->session()->getId();

            // Fast check via cache first (admin terminate sets this flag)
            if (Cache::has('session_terminated_' . $sessionId)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect(route('login'))->with('error',
                    'Session ของคุณถูกยกเลิกโดยผู้ดูแลระบบ กรุณา Login ใหม่'
                );
            }

            // Update last_activity timestamp on each request (throttled to every 60s via cache)
            $activityKey = 'session_activity_' . $sessionId;
            if (! Cache::has($activityKey)) {
                UserSession::where('session_id', $sessionId)
                    ->where('is_terminated', false)
                    ->update(['last_activity' => now()]);
                Cache::put($activityKey, true, now()->addSeconds(60));
            }
        }

        return $next($request);
    }
}
