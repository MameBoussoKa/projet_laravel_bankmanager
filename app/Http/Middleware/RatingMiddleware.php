<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RatingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log rate limit hits for monitoring
        if ($response->getStatusCode() === 429) {
            $user = $request->user();
            $userId = $user ? $user->id : 'anonymous';
            $ip = $request->ip();
            $endpoint = $request->path();

            Log::warning('Rate limit exceeded', [
                'user_id' => $userId,
                'ip' => $ip,
                'endpoint' => $endpoint,
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
                'trace_id' => uniqid()
            ]);
        }

        return $response;
    }
}
