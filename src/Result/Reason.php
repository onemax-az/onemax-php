<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Reason
{
    public const OK = 'ok';

    public const NOT_SUBSCRIBED = 'not_subscribed';

    public const LIMIT_REACHED = 'limit_reached';

    public const OFFER_UNAVAILABLE = 'offer_unavailable';

    public const INVALID_CODE = 'invalid_code';

    public const NOT_LINKED = 'not_linked';

    private const KNOWN = [
        self::OK,
        self::NOT_SUBSCRIBED,
        self::LIMIT_REACHED,
        self::OFFER_UNAVAILABLE,
        self::INVALID_CODE,
        self::NOT_LINKED,
    ];

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

    public function isOk(): bool
    {
        return $this->is(self::OK);
    }

    public function isNotSubscribed(): bool
    {
        return $this->is(self::NOT_SUBSCRIBED);
    }

    public function isLimitReached(): bool
    {
        return $this->is(self::LIMIT_REACHED);
    }

    public function isOfferUnavailable(): bool
    {
        return $this->is(self::OFFER_UNAVAILABLE);
    }

    public function isInvalidCode(): bool
    {
        return $this->is(self::INVALID_CODE);
    }

    public function isNotLinked(): bool
    {
        return $this->is(self::NOT_LINKED);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
