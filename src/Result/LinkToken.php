<?php

declare(strict_types=1);

namespace OneMax\Result;

final class LinkToken
{
    public function __construct(
        public readonly string $linkToken,
        public readonly bool $active,
    ) {
    }
}
