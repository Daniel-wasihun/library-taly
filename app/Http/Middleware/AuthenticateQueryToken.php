<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateQueryToken
{
    /**
     * Handle an incoming request.
     * Promotes query string token or api_token to the Authorization Bearer header
     * so that direct browser downloads, exports, and printable documents authenticate seamlessly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken() && ($request->filled('token') || $request->filled('api_token'))) {
            $token = $request->query('token') ?: $request->query('api_token');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
