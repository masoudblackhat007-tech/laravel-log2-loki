<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('adds a request id response header', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id');

    expect(Str::isUuid($response->headers->get('X-Request-Id')))->toBeTrue();
});

it('preserves a valid incoming request id', function () {
    $requestId = (string) Str::uuid();

    $response = $this
        ->withHeader('X-Request-Id', $requestId)
        ->get('/');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id', $requestId);
});

it('logs successful requests at info level', function () {
    Log::spy();

    $response = $this->get('/');

    $response->assertOk();

    Log::shouldHaveReceived('info')
        ->once()
        ->with('http_request', Mockery::on(function (array $context) use ($response) {
            return ($context['log_type'] ?? null) === 'http_request'
                && ($context['request_id'] ?? null) === $response->headers->get('X-Request-Id')
                && ($context['http']['status_code'] ?? null) === 200
                && ($context['http']['method'] ?? null) === 'GET'
                && array_key_exists('session_hash', $context['auth'] ?? []);
        }));

    Log::shouldNotHaveReceived('warning');
});
