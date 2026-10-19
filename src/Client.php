<?php

declare(strict_types=1);

namespace OneMax;

use OneMax\Exception\TransportException;
use OneMax\Http\CurlTransport;
use OneMax\Http\Request;
use OneMax\Http\Transport;
use OneMax\Internal\ErrorFactory;
use OneMax\Internal\Hydrator;
use OneMax\Internal\Json;
use OneMax\Result\Context;
use OneMax\Result\LinkStatus;
use OneMax\Result\LinkToken;
use OneMax\Result\Mode;
use OneMax\Result\Usage;
use OneMax\Result\Verification;

final class Client
{
    public const VERSION = '0.2.0';

    public const DEFAULT_BASE_URL = 'https://api.onemax.az/v1';

    public const DEFAULT_SITE_URL = 'https://onemax.az';

    private const USER_AGENT = 'onemax-php/' . self::VERSION;

    private const TEST_KEY_PREFIX = 'omx_test_';

    private const LOCALE_PREFIXES = ['az' => '', 'ru' => '/ru', 'en' => '/en'];

    private readonly string $baseUrl;

    private readonly string $siteUrl;

    private readonly Transport $transport;

    public function __construct(
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        string $siteUrl = self::DEFAULT_SITE_URL,
        private readonly float $timeout = 10.0,
        ?Transport $transport = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('The OneMax API key must not be empty.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
    }

    public function mode(): Mode
    {
        return new Mode(str_starts_with(trim($this->apiKey), self::TEST_KEY_PREFIX) ? Mode::TEST : Mode::LIVE);
    }

    public function context(): Context
    {
        return Hydrator::context($this->send('GET', '/integration/context'));
    }

    public function verifyCode(string $code, ?string $offerId = null): Verification
    {
        return Hydrator::verification(
            $this->send(
                'POST',
                '/integration/members/verify',
                self::withOptional(['code' => $code], 'offer_id', $offerId),
            ),
        );
    }

    public function usage(string $usageId): Usage
    {
        return Hydrator::usage($this->send('GET', self::usagePath($usageId)));
    }

    public function confirmUsage(string $usageId, ?string $reference = null): Usage
    {
        return Hydrator::usage(
            $this->send(
                'POST',
                self::usagePath($usageId) . '/confirm',
                self::withOptional([], 'reference', $reference),
            ),
        );
    }

    public function voidUsage(string $usageId): Usage
    {
        return Hydrator::usage($this->send('POST', self::usagePath($usageId) . '/void'));
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): LinkToken
    {
        return Hydrator::linkToken(
            $this->send('POST', '/integration/links/token', [
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
            ]),
        );
    }

    public function linkStatus(string $linkToken): LinkStatus
    {
        return Hydrator::linkStatus(
            $this->send('POST', '/integration/links/status', ['link_token' => $linkToken]),
        );
    }

    public function verifyLink(string $linkToken, ?string $offerId = null): Verification
    {
        return Hydrator::verification(
            $this->send(
                'POST',
                '/integration/links/verify',
                self::withOptional(['link_token' => $linkToken], 'offer_id', $offerId),
            ),
        );
    }

    public function revokeLink(string $linkToken): void
    {
        $this->send('POST', '/integration/links/revoke', ['link_token' => $linkToken]);
    }

    public function authorizeUrl(
        string $clientId,
        string $redirectUri,
        string $codeChallenge,
        ?string $state = null,
        ?string $locale = null,
    ): string {
        $prefix = self::LOCALE_PREFIXES[$locale ?? 'az'] ?? null;
        if ($prefix === null) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported locale "%s". Use az, ru or en.', (string) $locale),
            );
        }
        $query = self::withOptional(
            [
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ],
            'state',
            $state,
        );

        return $this->siteUrl . $prefix . '/baglanti?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function send(string $method, string $path, ?array $fields = null): array
    {
        $response = $this->transport->send(
            new Request(
                $method,
                $this->baseUrl . $path,
                $this->apiKey,
                self::USER_AGENT,
                $fields === null ? null : Json::encode($fields),
                $this->timeout,
            ),
        );
        if ($response->status < 200 || $response->status >= 300) {
            throw ErrorFactory::fromResponse($response);
        }
        $data = Json::decodeObject($response->body);
        if ($data === null) {
            throw new TransportException(
                sprintf('The API answered %d with a body that is not a JSON object.', $response->status),
            );
        }

        return $data;
    }

    private static function usagePath(string $usageId): string
    {
        return '/integration/usages/' . rawurlencode($usageId);
    }

    private static function withOptional(array $fields, string $key, ?string $value): array
    {
        if ($value !== null) {
            $fields[$key] = $value;
        }

        return $fields;
    }
}
