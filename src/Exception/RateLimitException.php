<?php

declare(strict_types=1);

namespace OneMax\Exception;

final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $status,
        string $errorCode,
        array $details = [],
        ?string $requestId = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errorCode, $details, $requestId);
    }
}
