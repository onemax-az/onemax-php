<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Usage
{
    public function __construct(
        public readonly string $usageId,
        public readonly UsageStatus $status,
        public readonly ?string $reference,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $confirmedAt,
        public readonly ?\DateTimeImmutable $voidedAt,
        public readonly ?Mode $mode = null,
    ) {
    }
}
