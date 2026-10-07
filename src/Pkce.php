<?php

declare(strict_types=1);

namespace OneMax;

final class Pkce
{
    private function __construct(
        public readonly string $verifier,
        public readonly string $challenge,
    ) {
    }

    public static function generate(): self
    {
        $verifier = self::encode(random_bytes(32));

        return new self($verifier, self::challengeFor($verifier));
    }

    public static function challengeFor(string $verifier): string
    {
        return self::encode(hash('sha256', $verifier, true));
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
