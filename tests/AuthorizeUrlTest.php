<?php

declare(strict_types=1);

namespace OneMax\Tests;

use OneMax\Client;
use OneMax\Tests\Support\FakeTransport;
use OneMax\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorizeUrlTest extends TestCase
{
    private const REDIRECT = 'https://shop.example/onemax/callback?from=checkout&x=a b';

    public static function locales(): array
    {
        return [
            'none' => [null, 'https://onemax.az/baglanti'],
            'az' => ['az', 'https://onemax.az/baglanti'],
            'ru' => ['ru', 'https://onemax.az/ru/baglanti'],
            'en' => ['en', 'https://onemax.az/en/baglanti'],
        ];
    }

    #[DataProvider('locales')]
    public function testTheAddressCarriesTheLocalePrefix(?string $locale, string $expectedPage): void
    {
        $url = self::client()->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1', 'state-1', $locale);

        self::assertSame($expectedPage, self::page($url));
        self::assertSame(
            [
                'response_type' => 'code',
                'client_id' => 'omx_client_1',
                'redirect_uri' => self::REDIRECT,
                'code_challenge' => 'challenge-1',
                'code_challenge_method' => 'S256',
                'state' => 'state-1',
            ],
            self::parameters($url),
        );
    }

    public function testTheRedirectAddressIsEncoded(): void
    {
        $url = self::client()->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1');

        self::assertStringContainsString(
            'redirect_uri=https%3A%2F%2Fshop.example%2Fonemax%2Fcallback%3Ffrom%3Dcheckout%26x%3Da%20b',
            $url,
        );
    }

    public function testStateIsLeftOutWhenNotGiven(): void
    {
        $url = self::client()->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1');

        self::assertStringNotContainsString('state', $url);
        self::assertSame(
            ['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method'],
            array_keys(self::parameters($url)),
        );
    }

    public function testAnUnknownLocaleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::client()->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1', null, 'de');
    }

    public function testTheSiteUrlIsConfigurableAndItsTrailingSlashIsStripped(): void
    {
        $client = new Client(Fixtures::API_KEY, siteUrl: 'http://localhost:3000/');

        $url = $client->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1', locale: 'ru');

        self::assertSame('http://localhost:3000/ru/baglanti', self::page($url));
    }

    public function testNoRequestIsMade(): void
    {
        $transport = FakeTransport::failing('The transport must not be used.');
        $client = new Client(Fixtures::API_KEY, transport: $transport);

        $client->authorizeUrl('omx_client_1', self::REDIRECT, 'challenge-1');

        $this->expectException(\LogicException::class);
        $transport->request();
    }

    private static function client(): Client
    {
        return new Client(Fixtures::API_KEY);
    }

    private static function page(string $url): string
    {
        return explode('?', $url, 2)[0];
    }

    private static function parameters(string $url): array
    {
        parse_str(explode('?', $url, 2)[1] ?? '', $parameters);

        return $parameters;
    }
}
