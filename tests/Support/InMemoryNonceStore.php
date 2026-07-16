<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use TropikalAI\Connect\Application\Ports\NonceStore;

/** Single-use nonce store for tests. */
final class InMemoryNonceStore implements NonceStore
{
    /** @var array<string, true> */
    private array $seen = [];

    public function claim(string $installationId, string $nonce, int $ttlSeconds): bool
    {
        $key = $installationId.':'.$nonce;
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }
}
