<?php

use Illuminate\Support\Str;

it('returns structured json for missing routes when json is expected', function () {
    $response = $this
        ->withHeader('Accept', 'application/json')
        ->get('/not-found-test');

    $response
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Request-Id')
        ->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details',
            ],
        ])
        ->assertJsonPath('error.code', 'HTTP_404')
        ->assertJsonPath('error.details', []);

    expect(Str::isUuid($response->headers->get('X-Request-Id')))->toBeTrue();
});
