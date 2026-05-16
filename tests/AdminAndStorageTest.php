<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\core\service\AdminAuthorizer;
use tropikal\connect\n2n\core\service\FileSecretStore;
use tropikal\connect\n2n\exception\UnauthorizedConnectException;

final class AdminAndStorageTest extends TestCase
{
    public function test_rejects_anonymous_and_non_admin_users(): void
    {
        $authorizer = new AdminAuthorizer;

        $this->expectException(UnauthorizedConnectException::class);
        $authorizer->requireAdmin(new FakeLoginContext(null));
    }

    public function test_admin_and_superadmin_are_allowed(): void
    {
        $authorizer = new AdminAuthorizer;

        self::assertSame('1', $authorizer->requireAdmin(new FakeLoginContext(new FakeUser(true, false)))['id']);
        self::assertTrue($authorizer->requireAdmin(new FakeLoginContext(new FakeUser(true, true)))['is_super_admin']);
    }

    public function test_state_store_encrypts_signing_secret_at_rest(): void
    {
        $path = sys_get_temp_dir().'/connect-n2n-test-'.bin2hex(random_bytes(4)).'/state.json';
        $store = new FileSecretStore($path, str_repeat('k', 32));
        $state = new ConnectN2nState(
            installationId: 'inst_123',
            publicId: 'public_123',
            serverSigningSecret: 'super-secret-signing-value',
            entityGrants: ['page' => ['read']],
        );

        $store->save($state);

        self::assertStringNotContainsString('super-secret-signing-value', (string) file_get_contents($path));
        self::assertSame('super-secret-signing-value', $store->load()->serverSigningSecret);
        self::assertSame(['read'], $store->load()->grantsFor('page'));
    }
}

final readonly class FakeLoginContext
{
    public function __construct(private ?FakeUser $user) {}

    public function hasCurrentUser(): bool
    {
        return $this->user !== null;
    }

    public function getCurrentUser(): ?FakeUser
    {
        return $this->user;
    }
}

final readonly class FakeUser
{
    public function __construct(private bool $admin, private bool $superAdmin) {}

    public function getId(): int
    {
        return 1;
    }

    public function getNick(): string
    {
        return 'admin';
    }

    public function getEmail(): string
    {
        return 'admin@example.com';
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }
}
