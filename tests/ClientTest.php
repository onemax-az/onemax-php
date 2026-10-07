<?php

declare(strict_types=1);

namespace OneMax\Tests;

use OneMax\Client;
use OneMax\Exception\OneMaxException;
use OneMax\Exception\TransportException;
use OneMax\Http\Request;
use OneMax\Result\Offer;
use OneMax\Result\Reason;
use OneMax\Result\UsageStatus;
use OneMax\Tests\Support\FakeTransport;
use OneMax\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testContextReadsTheVenueAndItsOffers(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::CONTEXT);

        $context = self::client($transport)->context();

        self::assertRequest($transport->request(), 'GET', '/integration/context', null);
        self::assertSame('Kofe Evi', $context->partnerName);
        self::assertSame('ven_1', $context->venueId);
        self::assertSame('Kofe Evi Nizami', $context->venueName);
        self::assertSame(2, $context->dailyLimit);
        self::assertCount(2, $context->offers);
        self::assertContainsOnlyInstancesOf(Offer::class, $context->offers);
        self::assertEquals(new Offer('off_1', '1+1 kofe', 'Kofe', true), $context->offers[0]);
        self::assertEquals(new Offer('off_2', '1+1 desert', 'Desert', false), $context->offers[1]);
    }

    public function testVerifyCodeSendsTheCodeAndTheOffer(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::VERIFICATION_USABLE);

        $verification = self::client($transport)->verifyCode('123456', 'off_1');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/members/verify',
            '{"code":"123456","offer_id":"off_1"}',
        );
        self::assertTrue($verification->active);
        self::assertTrue($verification->usable);
        self::assertTrue($verification->reason->isOk());
        self::assertSame(Reason::OK, $verification->reason->value);
        self::assertSame('usg_1', $verification->usageId);
        self::assertSame(2, $verification->dailyLimit);
    }

    public function testVerifyCodeLeavesTheOfferOutWhenNotGiven(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::VERIFICATION_REFUSED);

        $verification = self::client($transport)->verifyCode('123456');

        self::assertSame('{"code":"123456"}', $transport->request()->body);
        self::assertFalse($verification->active);
        self::assertFalse($verification->usable);
        self::assertTrue($verification->reason->isNotSubscribed());
        self::assertFalse($verification->reason->isOk());
        self::assertNull($verification->usageId);
    }

    public function testUsageReadsDatetimesAndNulls(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::USAGE_PENDING);

        $usage = self::client($transport)->usage('usg_1');

        self::assertRequest($transport->request(), 'GET', '/integration/usages/usg_1', null);
        self::assertSame('usg_1', $usage->usageId);
        self::assertTrue($usage->status->isPending());
        self::assertNull($usage->reference);
        self::assertSame('2026-10-07T17:16:11.906636+00:00', $usage->createdAt->format('Y-m-d\TH:i:s.uP'));
        self::assertNull($usage->confirmedAt);
        self::assertNull($usage->voidedAt);
    }

    public function testConfirmUsageSendsTheReference(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::USAGE_CONFIRMED);

        $usage = self::client($transport)->confirmUsage('usg_1', 'order-42');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/usages/usg_1/confirm',
            '{"reference":"order-42"}',
        );
        self::assertTrue($usage->status->isConfirmed());
        self::assertSame('order-42', $usage->reference);
        self::assertNotNull($usage->confirmedAt);
        self::assertSame('2026-10-07T21:20:00+04:00', $usage->confirmedAt->format(\DATE_ATOM));
        self::assertSame(
            '2026-10-07T17:20:00+00:00',
            $usage->confirmedAt->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM),
        );
    }

    public function testConfirmUsageWithoutAReferenceSendsAnEmptyObject(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::USAGE_CONFIRMED);

        self::client($transport)->confirmUsage('usg_1');

        self::assertSame('{}', $transport->request()->body);
        self::assertSame('application/json', $transport->request()->headers['Content-Type'] ?? null);
    }

    public function testVoidUsageSendsNoBody(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::USAGE_VOIDED);

        $usage = self::client($transport)->voidUsage('usg_1');

        self::assertRequest($transport->request(), 'POST', '/integration/usages/usg_1/void', null);
        self::assertTrue($usage->status->isVoided());
        self::assertNotNull($usage->voidedAt);
        self::assertSame('2026-10-07T17:18:00+00:00', $usage->voidedAt->format(\DATE_ATOM));
    }

    public function testExchangeCodeReturnsTheLinkToken(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::LINK_TOKEN);

        $link = self::client($transport)->exchangeCode('code-1', 'verifier-1', 'https://shop.example/cb?a=1');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/links/token',
            '{"code":"code-1","code_verifier":"verifier-1","redirect_uri":"https://shop.example/cb?a=1"}',
        );
        self::assertSame('omx_link_abc', $link->linkToken);
        self::assertTrue($link->active);
    }

    public function testLinkStatusAnswersLinkedAndActive(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::LINK_STATUS);

        $status = self::client($transport)->linkStatus('omx_link_abc');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/links/status',
            '{"link_token":"omx_link_abc"}',
        );
        self::assertTrue($status->linked);
        self::assertFalse($status->active);
    }

    public function testVerifyLinkSendsTheTokenAndTheOffer(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::VERIFICATION_USABLE);

        $verification = self::client($transport)->verifyLink('omx_link_abc', 'off_1');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/links/verify',
            '{"link_token":"omx_link_abc","offer_id":"off_1"}',
        );
        self::assertTrue($verification->usable);
        self::assertSame('usg_1', $verification->usageId);
    }

    public function testVerifyLinkLeavesTheOfferOutAndReadsNotLinked(): void
    {
        $transport = FakeTransport::answering(
            200,
            '{"active":false,"usable":false,"reason":"not_linked","usage_id":null,"daily_limit":2}',
        );

        $verification = self::client($transport)->verifyLink('omx_link_abc');

        self::assertSame('{"link_token":"omx_link_abc"}', $transport->request()->body);
        self::assertTrue($verification->reason->isNotLinked());
        self::assertFalse($verification->usable);
    }

    public function testRevokeLinkSendsTheToken(): void
    {
        $transport = FakeTransport::answering(200, '{"ok":true}');

        self::client($transport)->revokeLink('omx_link_abc');

        self::assertRequest(
            $transport->request(),
            'POST',
            '/integration/links/revoke',
            '{"link_token":"omx_link_abc"}',
        );
    }

    public function testPathParametersAreUrlEncoded(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::USAGE_PENDING);
        $client = self::client($transport);

        $client->usage('a/b c?d#e');
        self::assertSame(
            'https://api.test/v1/integration/usages/a%2Fb%20c%3Fd%23e',
            $transport->request()->url,
        );

        $client->confirmUsage('a/b');
        self::assertSame('https://api.test/v1/integration/usages/a%2Fb/confirm', $transport->request()->url);

        $client->voidUsage('../x');
        self::assertSame('https://api.test/v1/integration/usages/..%2Fx/void', $transport->request()->url);
    }

    public function testTheBaseUrlTrailingSlashDoesNotDoubleUp(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::CONTEXT);
        $client = new Client(Fixtures::API_KEY, 'https://api.test/v1/', transport: $transport);

        $client->context();

        self::assertSame('https://api.test/v1/integration/context', $transport->request()->url);
    }

    public function testTheDefaultsAreTheProductionAddressAndTenSeconds(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::CONTEXT);
        $client = new Client(Fixtures::API_KEY, transport: $transport);

        $client->context();

        self::assertSame('https://api.onemax.az/v1/integration/context', $transport->request()->url);
        self::assertSame(10.0, $transport->request()->timeout);
    }

    public function testTheTimeoutIsPassedToTheTransport(): void
    {
        $transport = FakeTransport::answering(200, Fixtures::CONTEXT);
        $client = new Client(Fixtures::API_KEY, timeout: 2.5, transport: $transport);

        $client->context();

        self::assertSame(2.5, $transport->request()->timeout);
    }

    public function testAnEmptyApiKeyIsRefusedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('');
    }

    public function testABlankApiKeyIsRefusedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('   ');
    }

    public function testAnUnknownReasonIsKept(): void
    {
        $transport = FakeTransport::answering(
            200,
            '{"active":true,"usable":false,"reason":"paused_by_venue","usage_id":null,"daily_limit":1}',
        );

        $reason = self::client($transport)->verifyCode('123456')->reason;

        self::assertSame('paused_by_venue', $reason->value);
        self::assertSame('paused_by_venue', (string) $reason);
        self::assertFalse($reason->isKnown());
        self::assertFalse($reason->isOk());
    }

    public function testAnUnknownStatusIsKept(): void
    {
        $transport = FakeTransport::answering(
            200,
            '{"usage_id":"usg_1","status":"expired","reference":null,'
            . '"created_at":"2026-10-07T17:16:11Z","confirmed_at":null,"voided_at":null}',
        );

        $status = self::client($transport)->usage('usg_1')->status;

        self::assertSame('expired', $status->value);
        self::assertFalse($status->isKnown());
        self::assertFalse($status->isPending());
        self::assertTrue($status->is('expired'));
    }

    public function testKnownValuesAreRecognised(): void
    {
        self::assertTrue((new Reason(Reason::LIMIT_REACHED))->isLimitReached());
        self::assertTrue((new Reason(Reason::OFFER_UNAVAILABLE))->isOfferUnavailable());
        self::assertTrue((new Reason(Reason::INVALID_CODE))->isInvalidCode());
        self::assertTrue((new Reason(Reason::NOT_LINKED))->isKnown());
        self::assertTrue((new UsageStatus(UsageStatus::CONFIRMED))->isKnown());
        self::assertSame('voided', (string) new UsageStatus(UsageStatus::VOIDED));
    }

    public function testAConnectionFailureRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::failing('Could not connect to the server'));

        try {
            $client->context();
            self::fail('A connection failure must raise.');
        } catch (TransportException $exception) {
            self::assertInstanceOf(OneMaxException::class, $exception);
            self::assertSame('Could not connect to the server', $exception->getMessage());
        }
    }

    public function testATimeoutRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::failing('Operation timed out after 10000 milliseconds'));

        $this->expectException(TransportException::class);

        $client->verifyCode('123456');
    }

    public function testANonJsonSuccessBodyRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::answering(200, '<html>Gateway</html>'));

        $this->expectException(TransportException::class);

        $client->context();
    }

    public function testAJsonScalarSuccessBodyRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::answering(200, '"ok"'));

        $this->expectException(TransportException::class);

        $client->revokeLink('omx_link_abc');
    }

    public function testASuccessBodyWithAMissingFieldRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::answering(200, '{"active":true}'));

        $this->expectException(TransportException::class);

        $client->verifyCode('123456');
    }

    public function testAMalformedDatetimeRaisesATransportException(): void
    {
        $client = self::client(FakeTransport::answering(
            200,
            '{"usage_id":"usg_1","status":"pending","reference":null,'
            . '"created_at":"not a moment","confirmed_at":null,"voided_at":null}',
        ));

        $this->expectException(TransportException::class);

        $client->usage('usg_1');
    }

    private static function client(FakeTransport $transport): Client
    {
        return new Client(Fixtures::API_KEY, 'https://api.test/v1', transport: $transport);
    }

    private static function assertRequest(Request $request, string $method, string $path, ?string $body): void
    {
        self::assertSame($method, $request->method);
        self::assertSame('https://api.test/v1' . $path, $request->url);
        self::assertSame($body, $request->body);
        $expectedHeaders = [
            'Authorization' => 'Bearer ' . Fixtures::API_KEY,
            'Accept' => 'application/json',
            'User-Agent' => 'onemax-php/0.1.0',
        ];
        if ($body !== null) {
            $expectedHeaders['Content-Type'] = 'application/json';
        }
        self::assertSame($expectedHeaders, $request->headers);
    }
}
