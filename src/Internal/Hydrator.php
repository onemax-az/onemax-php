<?php

declare(strict_types=1);

namespace OneMax\Internal;

use OneMax\Exception\TransportException;
use OneMax\Result\Context;
use OneMax\Result\LinkStatus;
use OneMax\Result\LinkToken;
use OneMax\Result\Mode;
use OneMax\Result\Offer;
use OneMax\Result\Reason;
use OneMax\Result\Usage;
use OneMax\Result\UsageStatus;
use OneMax\Result\Verification;

final class Hydrator
{
    public static function context(array $data): Context
    {
        $rawOffers = $data['offers'] ?? null;
        if (!is_array($rawOffers)) {
            throw self::unexpected('offers');
        }
        $offers = [];
        foreach ($rawOffers as $rawOffer) {
            if (!is_array($rawOffer)) {
                throw self::unexpected('offers');
            }
            $offers[] = new Offer(
                self::string($rawOffer, 'id'),
                self::string($rawOffer, 'headline'),
                self::string($rawOffer, 'title'),
                self::bool($rawOffer, 'open_now'),
            );
        }

        return new Context(
            self::string($data, 'partner_name'),
            self::string($data, 'venue_id'),
            self::string($data, 'venue_name'),
            self::int($data, 'daily_limit'),
            $offers,
            self::mode($data),
        );
    }

    public static function verification(array $data): Verification
    {
        return new Verification(
            self::bool($data, 'active'),
            self::bool($data, 'usable'),
            new Reason(self::string($data, 'reason')),
            self::nullableString($data, 'usage_id'),
            self::int($data, 'daily_limit'),
            self::mode($data),
        );
    }

    public static function usage(array $data): Usage
    {
        $createdAt = self::nullableDateTime($data, 'created_at');
        if ($createdAt === null) {
            throw self::unexpected('created_at');
        }

        return new Usage(
            self::string($data, 'usage_id'),
            new UsageStatus(self::string($data, 'status')),
            self::nullableString($data, 'reference'),
            $createdAt,
            self::nullableDateTime($data, 'confirmed_at'),
            self::nullableDateTime($data, 'voided_at'),
            self::mode($data),
        );
    }

    public static function linkToken(array $data): LinkToken
    {
        return new LinkToken(self::string($data, 'link_token'), self::bool($data, 'active'), self::mode($data));
    }

    public static function linkStatus(array $data): LinkStatus
    {
        return new LinkStatus(self::bool($data, 'linked'), self::bool($data, 'active'), self::mode($data));
    }

    private static function mode(array $data): ?Mode
    {
        $value = self::nullableString($data, 'mode');

        return $value === null ? null : new Mode($value);
    }

    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    private static function nullableString(array $data, string $key): ?string
    {
        return ($data[$key] ?? null) === null ? null : self::string($data, $key);
    }

    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    private static function nullableDateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::nullableString($data, $key);
        if ($value === null) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new TransportException(sprintf('The response field "%s" is not a datetime.', $key), 0, $exception);
        }
    }

    private static function unexpected(string $key): TransportException
    {
        return new TransportException(sprintf('The response field "%s" is missing or has an unexpected type.', $key));
    }
}
