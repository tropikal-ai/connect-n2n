<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

final class PublicPayloadGuard
{
    private const ALLOWED_KEYS = [
        'entity_key',
        'key',
        'key_id',
        'public_id',
        'resource_key',
    ];

    private const KEY_MARKERS = [
        'access_token',
        'api_key',
        'assertion',
        'bearer',
        'client_secret',
        'credential',
        'hmac',
        'password',
        'private',
        'refresh',
        'secret',
        'signature',
        'token',
    ];

    public static function assertPublicPayload(array $payload): void
    {
        self::walk($payload, []);
    }

    public static function assertPublicKey(string $key): void
    {
        if (self::isSensitiveKey($key)) {
            throw new \InvalidArgumentException("Public payload contains a server-only key: {$key}");
        }
    }

    public static function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $child) {
            $keyName = (string) $key;
            $redacted[$key] = self::isSensitiveKey($keyName) ? '[redacted]' : self::redact($child);
        }

        return $redacted;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        if (in_array($normalized, self::ALLOWED_KEYS, true)) {
            return false;
        }
        if ($normalized === 'key' || str_ends_with($normalized, '_key')) {
            return true;
        }

        foreach (self::KEY_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function walk(mixed $value, array $path): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                self::assertPublicKey((string) $key);
                self::walk($child, [...$path, (string) $key]);
            }

            return;
        }

        if (is_string($value) && str_contains($value, 'Bearer ')) {
            $location = implode('.', $path) ?: '<root>';
            throw new \InvalidArgumentException("Public payload contains a server-only value at {$location}");
        }
    }
}
