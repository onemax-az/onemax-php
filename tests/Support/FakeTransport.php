<?php

declare(strict_types=1);

namespace OneMax\Tests\Support;

use OneMax\Exception\TransportException;
use OneMax\Http\Request;
use OneMax\Http\Response;
use OneMax\Http\Transport;

final class FakeTransport implements Transport
{
    private ?Request $request = null;

    private function __construct(private readonly Response|TransportException $outcome)
    {
    }

    public static function answering(int $status, string $body): self
    {
        return new self(new Response($status, $body));
    }

    public static function failing(string $message): self
    {
        return new self(new TransportException($message));
    }

    public function send(Request $request): Response
    {
        $this->request = $request;
        if ($this->outcome instanceof TransportException) {
            throw $this->outcome;
        }

        return $this->outcome;
    }

    public function request(): Request
    {
        if ($this->request === null) {
            throw new \LogicException('No request was sent.');
        }

        return $this->request;
    }
}
