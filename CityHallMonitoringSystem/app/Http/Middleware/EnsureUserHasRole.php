<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user has one of the allowed roles.
     *
     * Usage in route: ->middleware('role:Admin,Encoder')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to perform this action.');
        }

        return $next($request);
    }
}

