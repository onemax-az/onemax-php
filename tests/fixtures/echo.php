<?php

declare(strict_types=1);

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';
$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';

if (str_starts_with($uri, '/slow')) {
    sleep(3);
}

if (str_starts_with($uri, '/missing')) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"error":{"code":"usage_not_found","message":"İstifadə tapılmadı","details":{},"request_id":"req_9"}}';

    return;
}

$lines = [$method . ' ' . $uri];
foreach (getallheaders() as $name => $value) {
    if (is_string($value)) {
        $lines[] = strtolower((string) $name) . ': ' . $value;
    }
}
$lines[] = 'body: ' . file_get_contents('php://input');

header('Content-Type: text/plain');
echo implode("\n", $lines);
