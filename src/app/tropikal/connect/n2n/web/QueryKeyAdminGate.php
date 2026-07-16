<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use n2n\web\http\Request;

/**
 * Allows admin access when the request carries the configured key
 * (?key=...), compared in constant time. Adequate for a demo/canary;
 * production sites should bind their own AdminGate to real authentication.
 */
final readonly class QueryKeyAdminGate implements AdminGate
{
    public function __construct(private string $adminKey)
    {
        if (strlen($this->adminKey) < 16) {
            throw new \InvalidArgumentException('Admin key must be at least 16 characters.');
        }
    }

    public function allows(Request $request): bool
    {
        $candidate = (string) ($request->getQuery()->get('key') ?? '');

        return $candidate !== '' && hash_equals($this->adminKey, $candidate);
    }

    public function linkParams(Request $request): array
    {
        return ['key' => $this->adminKey];
    }
}
