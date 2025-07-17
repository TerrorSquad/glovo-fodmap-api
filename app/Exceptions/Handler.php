<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (\Throwable $e): void {});
    }

    /**
     * Render an exception into an HTTP response for API requests.
     *
     * @param mixed $request
     */
    public function render($request, \Throwable $e): ?JsonResponse
    {
        // Only handle API requests
        if (! $request->is('api/*')) {
            return null;
        }

        // Handle validation exceptions
        if ($e instanceof ValidationException) {
            return response()->json([
                'error'   => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Handle HTTP exceptions
        if ($e instanceof HttpException) {
            return response()->json([
                'error'   => 'HTTP Error',
                'message' => $e->getMessage() ?: 'An error occurred.',
            ], $e->getStatusCode());
        }

        // Handle general exceptions
        if (config('app.debug')) {
            return response()->json([
                'error'   => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTrace(),
            ], 500);
        }

        return response()->json([
            'error'   => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.',
        ], 500);
    }
}
