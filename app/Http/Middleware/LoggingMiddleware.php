<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Log the incoming request
        Log::info('API Request Started', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString(),
            'operation' => $this->getOperationName($request),
            'resource' => $this->getResourceName($request),
        ]);

        $response = $next($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // Duration in milliseconds

        // Log the response
        Log::info('API Request Completed', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'timestamp' => now()->toISOString(),
            'operation' => $this->getOperationName($request),
            'resource' => $this->getResourceName($request),
        ]);

        return $response;
    }

    /**
     * Get the operation name from the request
     */
    private function getOperationName(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        // Map HTTP methods to operations
        $operations = [
            'GET' => 'READ',
            'POST' => 'CREATE',
            'PUT' => 'UPDATE',
            'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
        ];

        return $operations[$method] ?? 'UNKNOWN';
    }

    /**
     * Get the resource name from the request
     */
    private function getResourceName(Request $request): string
    {
        $path = $request->path();

        // Extract resource from API path
        if (preg_match('/\/api\/v\d+\/([^\/]+)/', $path, $matches)) {
            return strtoupper($matches[1]);
        }

        return 'UNKNOWN';
    }
}