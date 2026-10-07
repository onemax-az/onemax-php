<?php

declare(strict_types=1);

namespace OneMax\Exception;

class ApiException extends OneMaxException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly array $details = [],
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message);
    }
}
