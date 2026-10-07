<?php

declare(strict_types=1);

namespace OneMax\Http;

use OneMax\Exception\TransportException;

final class CurlTransport implements Transport
{
    public function send(Request $request): Response
    {
        if ($request->url === '' || $request->method === '') {
            throw new TransportException('A request needs a method and an address.');
        }
        $timeout = max(1, (int) round($request->timeout * 1000));
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            if (is_string($value)) {
                $headerLines[] = $name . ': ' . $value;
            }
        }

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $request->url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $request->method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_NOSIGNAL, true);
        curl_setopt($handle, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, $timeout);
        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        } elseif ($request->method !== 'GET') {
            $headerLines[] = 'Content-Length: 0';
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);

        $body = curl_exec($handle);
        if (!is_string($body)) {
            throw new TransportException(
                sprintf('The request to %s failed: %s', $request->url, curl_error($handle)),
                curl_errno($handle),
            );
        }

        return new Response((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body);
    }
}
