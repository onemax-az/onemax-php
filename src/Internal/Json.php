<?php

declare(strict_types=1);

namespace OneMax\Internal;

final class Json
{
    private const FLAGS = JSON_FORCE_OBJECT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR;

    public static function encode(array $fields): string
    {
        return json_encode($fields, self::FLAGS);
    }

    public static function decodeObject(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
