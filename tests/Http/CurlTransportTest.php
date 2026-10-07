<?php

declare(strict_types=1);

namespace OneMax\Tests\Http;

use OneMax\Client;
use OneMax\Exception\NotFoundException;
use OneMax\Exception\TransportException;
use OneMax\Http\CurlTransport;
use OneMax\Http\Request;
use OneMax\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('curl')]
final class CurlTransportTest extends TestCase
{
    private static mixed $server = null;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        self::$port = self::freePort();
        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, dirname(__DIR__) . '/fixtures/echo.php'],
            [['pipe', 'r'], ['file', self::nullDevice(), 'w'], ['file', self::nullDevice(), 'w']],
            $pipes,
        );
        if ($server === false) {
            self::fail('The stub server could not be started.');
        }
        self::$server = $server;
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errorCode, $errorMessage, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(50000);
        }
        self::fail('The stub server did not start listening.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
    }

    public function testAGetSendsTheHeadersAndNoBody(): void
    {
        $response = (new CurlTransport())->send(self::request('GET', '/v1/integration/context', null));

        self::assertSame(200, $response->status);
        self::assertStringStartsWith("GET /v1/integration/context\n", $response->body);
        self::assertStringContainsString("\nauthorization: Bearer " . Fixtures::API_KEY . "\n", $response->body);
        self::assertStringContainsString("\naccept: application/json\n", $response->body);
        self::assertStringContainsString("\nuser-agent: onemax-php/0.1.0\n", $response->body);
        self::assertStringNotContainsString('content-type', $response->body);
        self::assertStringEndsWith("\nbody: ", $response->body);
    }

    public function testAPostSendsItsJsonBody(): void
    {
        $response = (new CurlTransport())->send(
            self::request('POST', '/v1/integration/members/verify', '{"code":"123456"}'),
        );

        self::assertStringStartsWith("POST /v1/integration/members/verify\n", $response->body);
        self::assertStringContainsString("\ncontent-type: application/json\n", $response->body);
        self::assertStringContainsString("\ncontent-length: 17\n", $response->body);
        self::assertStringEndsWith("\nbody: {\"code\":\"123456\"}", $response->body);
    }

    public function testAPostWithoutABodySendsAnEmptyOne(): void
    {
        $response = (new CurlTransport())->send(self::request('POST', '/v1/integration/usages/usg_1/void', null));

        self::assertStringStartsWith("POST /v1/integration/usages/usg_1/void\n", $response->body);
        self::assertStringContainsString("\ncontent-length: 0\n", $response->body);
        self::assertStringNotContainsString('content-type', $response->body);
        self::assertStringEndsWith("\nbody: ", $response->body);
    }

    public function testAnErrorAnswerReachesTheClientAsItsException(): void
    {
        $client = new Client(Fixtures::API_KEY, self::base() . '/missing');

        try {
            $client->usage('usg_1');
            self::fail('The client did not raise.');
        } catch (NotFoundException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame('usage_not_found', $exception->errorCode);
            self::assertSame('İstifadə tapılmadı', $exception->getMessage());
            self::assertSame('req_9', $exception->requestId);
        }
    }

    public function testARefusedConnectionRaisesATransportException(): void
    {
        $request = new Request(
            'GET',
            'http://127.0.0.1:' . self::freePort() . '/v1/integration/context',
            Fixtures::API_KEY,
            'onemax-php/0.1.0',
            null,
            2.0,
        );

        $this->expectException(TransportException::class);

        (new CurlTransport())->send($request);
    }

    public function testATimeoutRaisesATransportException(): void
    {
        $request = new Request('GET', self::base() . '/slow', Fixtures::API_KEY, 'onemax-php/0.1.0', null, 0.3);
        $started = microtime(true);

        try {
            (new CurlTransport())->send($request);
            self::fail('The transport did not raise.');
        } catch (TransportException $exception) {
            self::assertSame(CURLE_OPERATION_TIMEDOUT, $exception->getCode());
            self::assertLessThan(2.5, microtime(true) - $started);
        }
    }

    private static function request(string $method, string $path, ?string $body): Request
    {
        return new Request($method, self::base() . $path, Fixtures::API_KEY, 'onemax-php/0.1.0', $body, 5.0);
    }

    private static function base(): string
    {
        return 'http://127.0.0.1:' . self::$port;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            self::fail('No free port could be reserved.');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }
}
