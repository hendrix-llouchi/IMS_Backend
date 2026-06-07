<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => $exception->errors(),
        ], 422);
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json(['message' => 'Method not allowed.'], 405);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json(['message' => 'Route not found.'], 404);
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return response()->json(['message' => 'Too many requests. Please slow down.'], 429);
        }

        return parent::render($request, $exception);
    }

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}