<?php

declare(strict_types=1);

namespace OneMax\Tests;

use OneMax\Client;
use OneMax\Exception\AccessDisabledException;
use OneMax\Exception\ApiException;
use OneMax\Exception\AuthenticationException;
use OneMax\Exception\ConflictException;
use OneMax\Exception\InvalidRequestException;
use OneMax\Exception\NotFoundException;
use OneMax\Exception\OneMaxException;
use OneMax\Exception\RateLimitException;
use OneMax\Exception\ServerException;
use OneMax\Tests\Support\FakeTransport;
use OneMax\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public static function statuses(): array
    {
        return [
            '400' => [400, 'offer_required', InvalidRequestException::class],
            '401' => [401, 'invalid_api_key', AuthenticationException::class],
            '403' => [403, 'api_access_disabled', AccessDisabledException::class],
            '404' => [404, 'usage_not_found', NotFoundException::class],
            '409' => [409, 'reference_used', ConflictException::class],
            '422' => [422, 'validation_error', InvalidRequestException::class],
            '429' => [429, 'rate_limited', RateLimitException::class],
            '500' => [500, 'internal_error', ServerException::class],
            '503' => [503, 'unavailable', ServerException::class],
            '418' => [418, 'teapot', ApiException::class],
        ];
    }

    #[DataProvider('statuses')]
    public function testEachStatusRaisesItsClass(int $status, string $code, string $class): void
    {
        $body = sprintf(
            '{"error":{"code":"%s","message":"İstifadə tapılmadı","details":{"field":"offer_id"},"request_id":"req_1"}}',
            $code,
        );

        $exception = self::failure($status, $body);

        self::assertSame($class, $exception::class);
        self::assertInstanceOf(OneMaxException::class, $exception);
        self::assertSame($status, $exception->status);
        self::assertSame($code, $exception->errorCode);
        self::assertSame('İstifadə tapılmadı', $exception->getMessage());
        self::assertSame(['field' => 'offer_id'], $exception->details);
        self::assertSame('req_1', $exception->requestId);
    }

    public function testANewCodeOnAKnownStatusStillRaisesTheRightClass(): void
    {
        $exception = self::failure(409, '{"error":{"code":"brand_new","message":"Yeni","details":{}}}');

        self::assertInstanceOf(ConflictException::class, $exception);
        self::assertSame('brand_new', $exception->errorCode);
        self::assertSame([], $exception->details);
        self::assertNull($exception->requestId);
    }

    public function testRateLimitExposesRetryAfter(): void
    {
        $exception = self::failure(
            429,
            '{"error":{"code":"rate_limited","message":"Çox sorğu","details":{"retry_after":17},"request_id":"req_2"}}',
        );

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame(17, $exception->retryAfter);
        self::assertSame(['retry_after' => 17], $exception->details);
    }

    public function testRateLimitWithoutRetryAfterLeavesItNull(): void
    {
        $exception = self::failure(429, '{"error":{"code":"rate_limited","message":"Çox sorğu","details":{}}}');

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertNull($exception->retryAfter);
    }

    #[DataProvider('statuses')]
    public function testANonJsonErrorBodyStillRaisesByStatus(int $status, string $code, string $class): void
    {
        $exception = self::failure($status, '<html>Bad gateway</html>');

        self::assertSame($class, $exception::class);
        self::assertSame($status, $exception->status);
        self::assertSame('unknown_error', $exception->errorCode);
        self::assertSame('<html>Bad gateway</html>', $exception->getMessage());
        self::assertSame([], $exception->details);
        self::assertNull($exception->requestId);
    }

    public function testAJsonBodyThatIsNotTheEnvelopeIsUnknown(): void
    {
        $exception = self::failure(500, '{"detail":"boom"}');

        self::assertInstanceOf(ServerException::class, $exception);
        self::assertSame('unknown_error', $exception->errorCode);
        self::assertSame('{"detail":"boom"}', $exception->getMessage());
    }

    public function testALongRawBodyIsTruncatedToTwoHundredCharacters(): void
    {
        $exception = self::failure(502, str_repeat('ə', 500));

        self::assertSame(str_repeat('ə', 200), $exception->getMessage());
    }

    public function testALongRawBodyThatIsNotUtf8IsTruncatedByBytes(): void
    {
        $exception = self::failure(502, str_repeat("\xff", 500));

        self::assertSame(200, strlen($exception->getMessage()));
    }

    private static function failure(int $status, string $body): ApiException
    {
        $client = new Client(Fixtures::API_KEY, transport: FakeTransport::answering($status, $body));

        try {
            $client->usage('usg_1');
        } catch (ApiException $exception) {
            return $exception;
        }

        self::fail('The client did not raise.');
    }
}
