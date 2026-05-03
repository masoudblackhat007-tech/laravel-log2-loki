<?php

use App\Logging\SensitiveDataRedactor;

it('redacts sensitive keys recursively', function () {
    $clean = SensitiveDataRedactor::sanitizeArray([
        'email' => 'user@example.com',
        'password' => 'secret-password',
        'authorization' => 'Bearer secret-token',
        'profile' => [
            'name' => 'Masoud',
            'api_key' => 'secret-api-key',
            'nested' => [
                'client_secret' => 'secret-client-value',
            ],
        ],
    ]);

    expect($clean['email'])->toBe('user@example.com')
        ->and($clean['password'])->toBe('[REDACTED]')
        ->and($clean['authorization'])->toBe('[REDACTED]')
        ->and($clean['profile']['name'])->toBe('Masoud')
        ->and($clean['profile']['api_key'])->toBe('[REDACTED]')
        ->and($clean['profile']['nested']['client_secret'])->toBe('[REDACTED]');
});

it('masks ipv4 addresses', function () {
    expect(SensitiveDataRedactor::maskIp('87.249.139.181'))
        ->toBe('87.249.139.0');
});

it('hashes identifiers without returning the raw value', function () {
    $raw = 'session-id-secret-value';

    $hash = SensitiveDataRedactor::hashIdentifier($raw);

    expect($hash)
        ->not->toBeNull()
        ->not->toBe($raw)
        ->and(strlen($hash))->toBe(32);
});

it('truncates long strings', function () {
    $clean = SensitiveDataRedactor::sanitizeArray([
        'message' => str_repeat('A', 600),
    ]);

    expect(strlen($clean['message']))->toBeLessThanOrEqual(503)
        ->and($clean['message'])->toEndWith('...');
});
