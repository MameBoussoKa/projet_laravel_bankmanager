<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiUserRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $key = 'user:' . $user->id;

        // 10 requests per day per user
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_RATE_LIMIT_EXCEEDED',
                    'message' => 'Limite de requêtes utilisateur dépassée. Veuillez réessayer demain.',
                    'details' => [
                        'retry_after' => RateLimiter::availableIn($key)
                    ],
                    'timestamp' => now()->toISOString(),
                    'path' => $request->path(),
                    'traceId' => uniqid()
                ]
            ], 429);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}