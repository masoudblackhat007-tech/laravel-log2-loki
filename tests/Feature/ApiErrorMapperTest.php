<?php

use App\Support\Errors\ApiErrorMapper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('maps unknown exceptions to a safe internal error', function () {
    $error = app(ApiErrorMapper::class)
        ->fromThrowable(new RuntimeException('DB_PASSWORD=super-secret-value'));

    expect($error->status)->toBe(500)
        ->and($error->code)->toBe('INTERNAL_ERROR')
        ->and($error->message)->toBe('Internal server error')
        ->and($error->message)->not->toContain('super-secret-value')
        ->and($error->details)->toBe([]);
});

it('maps database query exceptions to a safe database error', function () {
    $previous = new PDOException('SQLSTATE[HY000] Access denied with password=secret');

    $exception = new QueryException(
        connectionName: 'mysql',
        sql: 'select * from users where password = ?',
        bindings: ['secret'],
        previous: $previous
    );

    $error = app(ApiErrorMapper::class)->fromThrowable($exception);

    expect($error->status)->toBe(500)
        ->and($error->code)->toBe('DB_ERROR')
        ->and($error->message)->toBe('Database query error')
        ->and($error->message)->not->toContain('password')
        ->and($error->details)->toBe([]);
});

it('maps authentication exceptions', function () {
    $error = app(ApiErrorMapper::class)
        ->fromThrowable(new AuthenticationException());

    expect($error->status)->toBe(401)
        ->and($error->code)->toBe('AUTH_ERROR')
        ->and($error->message)->toBe('Unauthenticated')
        ->and($error->details)->toBe([]);
});

it('maps validation exceptions with validation details', function () {
    $exception = ValidationException::withMessages([
        'email' => ['The email field is required.'],
    ]);

    $error = app(ApiErrorMapper::class)->fromThrowable($exception);

    expect($error->status)->toBe(422)
        ->and($error->code)->toBe('VALIDATION_ERROR')
        ->and($error->message)->toBe('Validation failed')
        ->and($error->details)->toHaveKey('email');
});
