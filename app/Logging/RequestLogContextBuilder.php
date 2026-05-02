<?php

namespace App\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestLogContextBuilder
{
    private const MAX_UA_LEN = 200;

    public function build(Request $request, Response $response, string $requestId, int $durationMs): array
    {
        return [
            'log_type' => 'http_request',
            'request_id' => $requestId,
            'service' => $this->service(),
            'environment' => config('app.env'),
            'http' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName() ?? $request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'client_ip' => SensitiveDataRedactor::maskIp($request->ip()),
                'user_agent' => Str::limit((string) $request->userAgent(), self::MAX_UA_LEN, '...'),
            ],
            'auth' => $this->authContext($request),
        ];
    }

    private function authContext(Request $request): array
    {
        $user = null;

        try {
            $user = $request->user();
        } catch (\Throwable) {
            $user = null;
        }

        return [
            'user_id' => $user?->id,
            'session_hash' => $this->sessionHash(),
        ];
    }

    private function sessionHash(): ?string
    {
        try {
            if (! function_exists('session')) {
                return null;
            }

            $sessionId = session()->getId();

            return is_string($sessionId) && $sessionId !== ''
                ? SensitiveDataRedactor::hashIdentifier($sessionId)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function service(): string
    {
        $name = config('app.name');

        return is_string($name) && trim($name) !== ''
            ? trim($name)
            : 'Laravel';
    }
}
