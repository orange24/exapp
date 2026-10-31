<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the structural settings — companies, branches, users, permissions.
 *
 * Admin runs the day-to-day settings (sessions, daily limit); who exists in
 * the system and what they may do is superadmin's alone. SuperAdminMenuSeeder
 * hides the menus to match, but hiding a menu is not access control.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Access denied. Super admin role required.');

        return $next($request);
    }
}
