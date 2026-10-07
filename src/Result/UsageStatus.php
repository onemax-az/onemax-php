<?php

declare(strict_types=1);

namespace OneMax\Result;

final class UsageStatus
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const VOIDED = 'voided';

    private const KNOWN = [self::PENDING, self::CONFIRMED, self::VOIDED];

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

    public function isPending(): bool
    {
        return $this->is(self::PENDING);
    }

    public function isConfirmed(): bool
    {
        return $this->is(self::CONFIRMED);
    }

    public function isVoided(): bool
    {
        return $this->is(self::VOIDED);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
