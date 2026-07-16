<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\nonce;

use TropikalAI\Connect\Application\Ports\NonceStore as ConnectNonceStore;

/**
 * Cluster-safe nonce store. A claim is a single INSERT guarded by the primary
 * key (installation_id, nonce_hash): the database's unique constraint makes it
 * atomic across every node sharing the database, so a signed request can never
 * be replayed on another node. Expired rows are pruned lazily.
 *
 * Works on SQLite and MySQL. Nonces are stored hashed.
 */
final readonly class PdoNonceStore implements ConnectNonceStore
{
    /** @var \Closure(): int */
    private \Closure $now;

    /** @param null|callable(): int $now clock override for tests */
    public function __construct(
        private \PDO $pdo,
        private string $table = 'tropikal_connect_nonces',
        ?callable $now = null,
    ) {
        $this->now = $now === null ? time(...) : $now(...);
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                installation_id VARCHAR(120) NOT NULL,
                nonce_hash CHAR(64) NOT NULL,
                expires_at INTEGER NOT NULL,
                PRIMARY KEY (installation_id, nonce_hash)
            )"
        );
    }

    public function claim(string $installationId, string $nonce, int $ttlSeconds): bool
    {
        $now = ($this->now)();
        $hash = hash('sha256', $nonce);

        // lazy prune: expired rows never block a fresh claim and don't accumulate
        $prune = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires_at < ?");
        $prune->execute([$now]);

        $insert = $this->pdo->prepare(
            "INSERT INTO {$this->table} (installation_id, nonce_hash, expires_at) VALUES (?, ?, ?)"
        );

        try {
            return $insert->execute([$installationId, $hash, $now + max(1, $ttlSeconds)]);
        } catch (\PDOException) {
            return false; // unique-constraint violation: nonce already used
        }
    }
}
