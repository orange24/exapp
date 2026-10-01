<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes by a module/action permission from the permissions table.
 *
 * Same reasoning as EnsureUserIsAdmin: the sidebar already hides these entries
 * from roles that lack the permission, but hiding a menu is not access control.
 *
 * The sanction reports expose customer identity documents alongside the
 * designated-persons matches recorded against them, so an authenticated user
 * from any role could otherwise read the whole compliance trail by typing a URL.
 *
 * Usage: ->middleware('permission:module7,read')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, string $action): Response
    {
        abort_unless(
            $request->user()?->hasPermission($module, $action),
            403,
            'Access denied. Missing permission: ' . $module . '/' . $action
        );

        return $next($request);
    }
}
