<?php

declare(strict_types=1);

namespace OneMax\Result;

final class LinkStatus
{
    public function __construct(
        public readonly bool $linked,
        public readonly bool $active,
        public readonly ?Mode $mode = null,
    ) {
    }
}
