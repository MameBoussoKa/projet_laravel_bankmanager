<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseFormat
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add standard headers
        $response->headers->set('X-API-Version', 'v1');
        $response->headers->set('X-Request-ID', uniqid());
        $response->headers->set('Accept-Language', $request->header('Accept-Language', 'fr-FR'));

        // Only format JSON responses
        if ($response->headers->get('Content-Type') === 'application/json') {
            $content = json_decode($response->getContent(), true);

            // If it's already in the expected format, don't modify
            if (isset($content['success'])) {
                return $response;
            }

            // Format successful responses
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $formatted = [
                    'success' => true,
                    'data' => $content,
                    'timestamp' => now()->toISOString(),
                    'path' => $request->path(),
                    'traceId' => uniqid()
                ];
                $response->setContent(json_encode($formatted));
            }
            // Format error responses
            elseif ($response->getStatusCode() >= 400) {
                $formatted = [
                    'success' => false,
                    'error' => [
                        'code' => $this->getErrorCode($response->getStatusCode()),
                        'message' => $content['message'] ?? 'Une erreur est survenue',
                        'details' => isset($content['errors']) ? $content['errors'] : (isset($content['details']) ? $content['details'] : []),
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                        'traceId' => uniqid()
                    ]
                ];
                $response->setContent(json_encode($formatted));
            }
        }

        return $response;
    }

    /**
     * Get standardized error code based on HTTP status
     */
    private function getErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR'
        };
    }
}