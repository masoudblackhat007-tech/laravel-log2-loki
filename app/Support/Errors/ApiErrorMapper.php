<?php

namespace App\Support\Errors;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrorMapper
{
    public function fromThrowable(Throwable $e): ApiError
    {
        if ($e instanceof ValidationException) {
            return new ApiError(
                status: 422,
                code: 'VALIDATION_ERROR',
                message: 'Validation failed',
                details: $e->errors()
            );
        }

        if ($e instanceof AuthenticationException) {
            return new ApiError(
                status: 401,
                code: 'AUTH_ERROR',
                message: 'Unauthenticated',
            );
        }

        if ($e instanceof HttpResponseException && method_exists($e, 'getResponse')) {
            $response = $e->getResponse();

            return new ApiError(
                status: $response->getStatusCode(),
                code: 'HTTP_'.$response->getStatusCode(),
                message: 'HTTP response error',
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return new ApiError(
                status: $status,
                code: 'HTTP_'.$status,
                message: $this->safeHttpMessage($e),
            );
        }

        if ($e instanceof QueryException) {
            return new ApiError(
                status: 500,
                code: 'DB_ERROR',
                message: 'Database query error',
            );
        }

        return new ApiError(
            status: 500,
            code: 'INTERNAL_ERROR',
            message: 'Internal server error',
        );
    }

    private function safeHttpMessage(HttpExceptionInterface $e): string
    {
        $message = trim((string) $e->getMessage());

        if ($message === '') {
            return 'HTTP error';
        }

        return Str::limit($message, 200, '...');
    }
}
