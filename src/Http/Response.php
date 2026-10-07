<?php

declare(strict_types=1);

namespace OneMax\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
