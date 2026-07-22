<?php
namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiExceptionHandler
{
    public static function render(Throwable $e, Request $request)
    {
        // Let Laravel handle non-API requests
        if (! $request->is('api/*')) {
            return null;
        }

        Log::error($e);

        return match (true) {
            $e instanceof ValidationException => self::validation($e),

            $e instanceof AuthenticationException => self::error(
                'Unauthenticated.',
                401
            ),

            $e instanceof AuthorizationException => self::error(
                'Forbidden.',
                403
            ),

            $e instanceof NotFoundHttpException => self::error(
                'Resource not found.',
                404
            ),

            $e instanceof QueryException => self::error(
                'Database error.',
                500
            ),

            $e instanceof HttpExceptionInterface => self::error(
                $e->getMessage() ?: 'HTTP Error',
                $e->getStatusCode()
            ),

            default => self::error(
                app()->isProduction()
                    ? 'Something went wrong.'
                    : $e->getMessage(),
                500
            ),
        };
    }

    private static function validation(ValidationException $e)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    }

    private static function error(
        string $message,
        int $status,
        array $errors = []
    ) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => empty($errors) ? null : $errors,
        ], $status);
    }
}