<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Format successful response
     */
    protected function successResponse($data = null, string $message = null, int $statusCode = 200): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success' => true,
            'timestamp' => now()->toISOString(),
            'path' => request()->path(),
            'traceId' => uniqid()
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Format error response
     */
    protected function errorResponse(string $code, string $message, array $details = [], int $statusCode = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'timestamp' => now()->toISOString(),
                'path' => request()->path(),
                'traceId' => uniqid()
            ]
        ], $statusCode);
    }

    /**
     * Format paginated response
     */
    protected function paginatedResponse($items, $paginationData, string $message = null): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $items,
            'pagination' => $paginationData,
            'timestamp' => now()->toISOString(),
            'path' => request()->path(),
            'traceId' => uniqid()
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }
}