<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     *
     * This middleware checks if a user is authenticated but doesn't require it.
     * If authenticated, the user will be available via $request->user().
     * If not authenticated, the request continues normally as a guest.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via session
        // No action needed - Laravel's session guard automatically
        // sets Auth::user() if session exists

        // Simply continue with the request
        // Auth::check() will return true if authenticated, false if guest
        return $next($request);
    }
}
