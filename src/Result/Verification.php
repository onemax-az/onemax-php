<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Verification
{
    public function __construct(
        public readonly bool $active,
        public readonly bool $usable,
        public readonly Reason $reason,
        public readonly ?string $usageId,
        public readonly int $dailyLimit,
    ) {
    }
}
