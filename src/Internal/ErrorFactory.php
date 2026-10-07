<?php

declare(strict_types=1);

namespace OneMax\Internal;

use OneMax\Exception\AccessDisabledException;
use OneMax\Exception\ApiException;
use OneMax\Exception\AuthenticationException;
use OneMax\Exception\ConflictException;
use OneMax\Exception\InvalidRequestException;
use OneMax\Exception\NotFoundException;
use OneMax\Exception\RateLimitException;
use OneMax\Exception\ServerException;
use OneMax\Http\Response;

final class ErrorFactory
{
    private const UNKNOWN_CODE = 'unknown_error';

    private const MESSAGE_LIMIT = 200;

    public static function fromResponse(Response $response): ApiException
    {
        $status = $response->status;
        $error = (Json::decodeObject($response->body) ?? [])['error'] ?? null;
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $message = is_array($error) ? ($error['message'] ?? null) : null;
        $details = [];
        $requestId = null;

        if (is_array($error) && is_string($code) && is_string($message)) {
            $rawDetails = $error['details'] ?? null;
            $rawRequestId = $error['request_id'] ?? null;
            $details = is_array($rawDetails) ? $rawDetails : [];
            $requestId = is_string($rawRequestId) ? $rawRequestId : null;
        } else {
            $code = self::UNKNOWN_CODE;
            $message = self::truncate($response->body);
        }

        $retryAfter = $details['retry_after'] ?? null;

        return match (true) {
            $status === 400, $status === 422 => new InvalidRequestException($message, $status, $code, $details, $requestId),
            $status === 401 => new AuthenticationException($message, $status, $code, $details, $requestId),
            $status === 403 => new AccessDisabledException($message, $status, $code, $details, $requestId),
            $status === 404 => new NotFoundException($message, $status, $code, $details, $requestId),
            $status === 409 => new ConflictException($message, $status, $code, $details, $requestId),
            $status === 429 => new RateLimitException(
                $message,
                $status,
                $code,
                $details,
                $requestId,
                is_int($retryAfter) ? $retryAfter : null,
            ),
            $status >= 500 => new ServerException($message, $status, $code, $details, $requestId),
            default => new ApiException($message, $status, $code, $details, $requestId),
        };
    }

    private static function truncate(string $text): string
    {
        if (preg_match('/^.{0,' . self::MESSAGE_LIMIT . '}/su', $text, $match) === 1) {
            return $match[0];
        }

        return substr($text, 0, self::MESSAGE_LIMIT);
    }
}
