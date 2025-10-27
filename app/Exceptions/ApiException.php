<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected array $details = [];
    protected string $errorCode;

    public function __construct(
        string $message = '',
        string $errorCode = 'API_ERROR',
        array $details = [],
        int $code = 400,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->message,
                'details' => $this->details,
                'timestamp' => now()->toISOString(),
                'path' => request()->path(),
                'traceId' => uniqid()
            ]
        ], $this->code);
    }
}
