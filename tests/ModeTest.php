<?php

declare(strict_types=1);

namespace OneMax\Tests;

use OneMax\Client;
use OneMax\Result\Mode;
use OneMax\Tests\Support\FakeTransport;
use OneMax\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModeTest extends TestCase
{
    public function testTheClientKnowsTheModeOfItsKey(): void
    {
        self::assertTrue((new Client('omx_test_abc'))->mode()->isTest());
        self::assertTrue((new Client('  omx_test_abc  '))->mode()->isTest());
        self::assertTrue((new Client(Fixtures::API_KEY))->mode()->isLive());
        self::assertSame(Mode::LIVE, (new Client('omx_other_abc'))->mode()->value);
    }

    public function testATestKeyIsSentAsItIs(): void
    {
        $transport = FakeTransport::answering(
            200,
            '{"mode":"test","active":true,"usable":true,"reason":"ok","usage_id":"usg_1","daily_limit":1}',
        );

        $verification = (new Client('omx_test_abc', 'https://api.test/v1', transport: $transport))->verifyCode('000001');

        self::assertSame('Bearer omx_test_abc', $transport->request()->headers['Authorization']);
        self::assertNotNull($verification->mode);
        self::assertTrue($verification->mode->isTest());
    }

    public static function answers(): array
    {
        return [
            'context' => [Fixtures::CONTEXT, static fn (Client $client) => $client->context()->mode],
            'usage' => [Fixtures::USAGE_PENDING, static fn (Client $client) => $client->usage('usg_1')->mode],
            'token' => [
                Fixtures::LINK_TOKEN,
                static fn (Client $client) => $client->exchangeCode('c', 'v', 'https://a.example')->mode,
            ],
            'status' => [Fixtures::LINK_STATUS, static fn (Client $client) => $client->linkStatus('omx_link_x')->mode],
        ];
    }

    #[DataProvider('answers')]
    public function testEveryResultCarriesTheModeTheApiAnsweredIn(string $body, \Closure $read): void
    {
        $withMode = static fn (string $mode): string => substr($body, 0, -1) . ',"mode":"' . $mode . '"}';

        self::assertSame(Mode::LIVE, self::modeOf($read, $withMode('live'))?->value);
        self::assertNull(self::modeOf($read, $body));
        $unknown = self::modeOf($read, $withMode('staging'));
        self::assertSame('staging', $unknown?->value);
        self::assertFalse($unknown->isKnown());
    }

    private static function modeOf(\Closure $read, string $body): ?Mode
    {
        $mode = $read(self::client($body));

        return $mode instanceof Mode ? $mode : null;
    }

    private static function client(string $body): Client
    {
        return new Client(Fixtures::API_KEY, 'https://api.test/v1', transport: FakeTransport::answering(200, $body));
    }
}
