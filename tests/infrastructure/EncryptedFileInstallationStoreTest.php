<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\infrastructure;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\oauth\PendingAuthorization;
use tropikal\connect\n2n\infrastructure\persistence\EncryptedFileInstallationStore;

final class EncryptedFileInstallationStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/connect-n2n-store-'.bin2hex(random_bytes(4)).'/state.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
    }

    public function test_round_trips_installation_and_pending(): void
    {
        $store = $this->store();
        $store->saveInstallation(new Installation('inst_1', 'pub_1', 'bfs_secret', ['article'], ['article' => ['read']]));
        $store->savePending(new PendingAuthorization('client_1', 'hash_1', 'verifier_1', new \DateTimeImmutable('+10 minutes')));

        $reloaded = $this->store(); // fresh instance, reads from disk
        self::assertTrue($reloaded->loadInstallation()->isConnected());
        self::assertSame('bfs_secret', $reloaded->loadInstallation()->signingSecret);
        self::assertSame(['article' => ['read']], $reloaded->loadInstallation()->resourcePermissions);
        self::assertSame('client_1', $reloaded->loadPending()?->clientId);
        self::assertSame('verifier_1', $reloaded->loadPending()?->codeVerifier);
    }

    public function test_secrets_never_touch_disk_in_plaintext(): void
    {
        $this->store()->saveInstallation(new Installation('inst_1', 'pub_1', 'bfs_super_secret_value', [], []));

        $raw = (string) file_get_contents($this->path);
        self::assertStringNotContainsString('bfs_super_secret_value', $raw);
        self::assertStringNotContainsString(base64_encode('bfs_super_secret_value'), $raw);
    }

    public function test_wrong_key_fails_closed_to_disconnected(): void
    {
        $this->store()->saveInstallation(new Installation('inst_1', 'pub_1', 'bfs_secret', [], []));

        $wrongKey = new EncryptedFileInstallationStore($this->path, str_repeat('w', 32));
        self::assertFalse($wrongKey->loadInstallation()->isConnected());
    }

    public function test_missing_file_means_disconnected(): void
    {
        $store = $this->store();
        self::assertFalse($store->loadInstallation()->isConnected());
        self::assertNull($store->loadPending());
    }

    public function test_rejects_short_encryption_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EncryptedFileInstallationStore($this->path, 'short');
    }

    private function store(): EncryptedFileInstallationStore
    {
        return new EncryptedFileInstallationStore($this->path, str_repeat('k', 32));
    }
}
