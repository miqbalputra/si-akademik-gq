<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetDefaultGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        // Octane keeps the auth manager alive between requests; start each new
        // request on the public portal guard before Filament selects admin.
        Auth::shouldUse('web');

        try {
            return $next($request);
        } finally {
            // Also restore the manager after Filament or auth:admin changes its
            // default. This keeps the next Octane request and test login on web.
            Auth::shouldUse('web');
        }
    }
}
