<?php

use App\Http\Middleware\RequestContextLogging;
use App\Support\Errors\ApiErrorMapper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestContextLogging::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $apiError = app(ApiErrorMapper::class)->fromThrowable($e);

            return response()->json([
                'error' => [
                    'code' => $apiError->code,
                    'message' => $apiError->message,
                    'details' => $apiError->details,
                ],
            ], $apiError->status);
        });
    })->create();
