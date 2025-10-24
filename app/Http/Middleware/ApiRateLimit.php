<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->ip();

        // 100 requests per minute per IP
        if (RateLimiter::tooManyAttempts($key, 100)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
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