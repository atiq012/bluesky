<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    // After JWT auth: blocked user OR blocked agency → force logout.
    public function handle(Request $request, Closure $next): Response
    {
        // Logout must still blacklist the JWT even when account is blocked.
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $message = $user->sessionBlockMessage();
        if ($message === null) {
            return $next($request);
        }

        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => ['force_logout' => true],
        ], 401);
    }
}
