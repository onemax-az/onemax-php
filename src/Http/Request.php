<?php

declare(strict_types=1);

namespace OneMax\Http;

final class Request
{
    public readonly array $headers;

    public function __construct(
        public readonly string $method,
        public readonly string $url,
        string $apiKey,
        string $userAgent,
        public readonly ?string $body,
        public readonly float $timeout,
    ) {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $userAgent,
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        $this->headers = $headers;
    }
}
