<?php

declare(strict_types=1);

namespace OneMax\Result;

final class Offer
{
    public function __construct(
        public readonly string $id,
        public readonly string $headline,
        public readonly string $title,
        public readonly bool $openNow,
    ) {
    }
}
