<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Mode
{
    public const LIVE = 'live';

    public const TEST = 'test';

    private const KNOWN = [self::LIVE, self::TEST];

    public function __construct(public readonly string $value)
    {
    }

    public function is(string $value): bool
    {
        return $this->value === $value;
    }

    public function isKnown(): bool
    {
        return in_array($this->value, self::KNOWN, true);
    }

    public function isLive(): bool
    {
        return $this->is(self::LIVE);
    }

    public function isTest(): bool
    {
        return $this->is(self::TEST);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
