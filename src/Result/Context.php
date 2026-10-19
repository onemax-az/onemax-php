<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Context
{
    public function __construct(
        public readonly string $partnerName,
        public readonly string $venueId,
        public readonly string $venueName,
        public readonly int $dailyLimit,
        public readonly array $offers,
        public readonly ?Mode $mode = null,
    ) {
    }
}
