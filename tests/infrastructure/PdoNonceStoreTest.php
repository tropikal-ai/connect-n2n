<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\infrastructure;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\infrastructure\nonce\PdoNonceStore;

/**
 * The cluster-safe nonce store: single-use enforcement backed by a database
 * unique constraint, so replay protection holds across every node that shares
 * the database — closing the per-host limitation of the file store.
 */
final class PdoNonceStoreTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function test_claims_a_fresh_nonce_once(): void
    {
        $store = new PdoNonceStore($this->pdo);

        self::assertTrue($store->claim('inst_1', 'nonce-a', 300));
        self::assertFalse($store->claim('inst_1', 'nonce-a', 300), 'replay must be rejected');
    }

    public function test_same_nonce_is_independent_per_installation(): void
    {
        $store = new PdoNonceStore($this->pdo);

        self::assertTrue($store->claim('inst_1', 'nonce-a', 300));
        self::assertTrue($store->claim('inst_2', 'nonce-a', 300));
    }

    public function test_expired_nonce_can_be_claimed_again(): void
    {
        $store = new PdoNonceStore($this->pdo, now: fn (): int => 1_000);
        self::assertTrue($store->claim('inst_1', 'nonce-a', 300));

        $later = new PdoNonceStore($this->pdo, now: fn (): int => 1_000 + 301);
        self::assertTrue($later->claim('inst_1', 'nonce-a', 300), 'expired rows are pruned');
    }

    public function test_two_stores_on_one_database_share_replay_protection(): void
    {
        // simulates two web nodes sharing the database
        $nodeA = new PdoNonceStore($this->pdo);
        $nodeB = new PdoNonceStore($this->pdo);

        self::assertTrue($nodeA->claim('inst_1', 'nonce-x', 300));
        self::assertFalse($nodeB->claim('inst_1', 'nonce-x', 300), 'other node must see the claim');
    }
}
