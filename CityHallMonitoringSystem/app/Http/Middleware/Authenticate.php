<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * For this project, auth failures for API routes should never redirect.
     * Returning null prevents attempts to generate a missing named "login" route.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}

