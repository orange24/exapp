<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the card reader setup page.
 *
 * Not expressible as permission:module1,write — staff must reach this page but
 * hold no user-management permission, and auditor holds read on every module
 * without ever sitting at a counter. The rule lives on the User model so the
 * sidebar seeder and this door read the same sentence; see
 * User::canManageCardReaders().
 *
 * Usage: ->middleware('manage-card-readers')
 */
class EnsureCanManageCardReaders
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageCardReaders() ?? false,
            403,
            'Access denied. Card reader setup is for counter staff and administrators.'
        );

        return $next($request);
    }
}
