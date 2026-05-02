<?php

namespace App\Support\Errors;

final readonly class ApiError
{
    public function __construct(
        public int $status,
        public string $code,
        public string $message,
        public array $details = [],
    ) {
    }
}
