<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Settings routes (users, branches, permissions, sessions).
 *
 * MenuSeeder hides these from every role but admin/superadmin, but hiding a
 * menu is not access control — without this, any authenticated user could
 * reach them by typing the URL.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Access denied. Admin role required.');

        return $next($request);
    }
}
