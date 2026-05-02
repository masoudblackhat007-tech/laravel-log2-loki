<?php

namespace App\Http\Middleware;

use App\Logging\RequestLogContextBuilder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestContextLogging
{
    public function __construct(
        private readonly RequestLogContextBuilder $contextBuilder
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->resolveRequestId($request);

        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $context = $this->contextBuilder->build(
            request: $request,
            response: $response,
            requestId: $requestId,
            durationMs: $durationMs
        );

        if ($response->getStatusCode() >= 400 || $durationMs >= 1000) {
            Log::warning('http_request', $context);
        } else {
            Log::info('http_request', $context);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get('X-Request-Id');

        if (is_string($incoming) && preg_match('/^[a-zA-Z0-9._-]{8,80}$/', $incoming)) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
