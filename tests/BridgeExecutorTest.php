<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\core\service\AuditLogger;
use tropikal\connect\n2n\core\service\BridgeExecutor;
use tropikal\connect\n2n\core\service\EntityDiscoveryService;
use tropikal\connect\n2n\core\service\EntityFieldPolicy;
use tropikal\connect\n2n\core\service\RocketSchemaMapper;
use tropikal\connect\n2n\dto\BridgeRequest;
use tropikal\connect\n2n\tests\Support\FakeAddCommand;
use tropikal\connect\n2n\tests\Support\FakeCollection;
use tropikal\connect\n2n\tests\Support\FakeCommand;
use tropikal\connect\n2n\tests\Support\FakeDeleteCommand;
use tropikal\connect\n2n\tests\Support\FakeEditCommand;
use tropikal\connect\n2n\tests\Support\FakeEiType;
use tropikal\connect\n2n\tests\Support\FakeMask;
use tropikal\connect\n2n\tests\Support\FakeOverviewCommand;
use tropikal\connect\n2n\tests\Support\FakeProp;
use tropikal\connect\n2n\tests\Support\FakeRocket;
use tropikal\connect\n2n\tests\Support\FakeSpec;
use tropikal\connect\n2n\tests\Support\FakeStringNature;
use tropikal\connect\n2n\tests\Support\InMemoryRocketEntityAdapter;
use tropikal\connect\n2n\tests\Support\PageEntity;

final class BridgeExecutorTest extends TestCase
{
    public function test_read_projects_declared_fields_only(): void
    {
        $executor = $this->executor(new InMemoryRocketEntityAdapter([
            'page' => ['1' => ['id' => '1', 'title' => 'Hello', 'private_note' => 'hidden']],
        ]));
        $state = $this->state(['page' => ['read']]);

        $response = $executor->execute(new BridgeRequest('rocket.entity.get', 'page', ['id' => '1']), $state);

        self::assertSame(200, $response->status);
        self::assertSame(['id' => '1', 'title' => 'Hello'], $response->body['data']);
    }

    public function test_write_rejects_unknown_fields(): void
    {
        $executor = $this->executor(new InMemoryRocketEntityAdapter);
        $state = $this->state(['page' => ['write']]);

        $response = $executor->execute(new BridgeRequest('rocket.entity.create', 'page', ['title' => 'Hello', 'unknown' => true]), $state);

        self::assertSame(422, $response->status);
        self::assertSame('validation_error', $response->body['error']);
    }

    public function test_write_does_not_allow_delete_without_delete_grant(): void
    {
        $executor = $this->executor(new InMemoryRocketEntityAdapter(['page' => ['1' => ['id' => '1', 'title' => 'Hello']]]));
        $state = $this->state(['page' => ['write']]);

        $response = $executor->execute(new BridgeRequest('rocket.entity.delete', 'page', ['id' => '1']), $state);

        self::assertSame(403, $response->status);
        self::assertSame('entity_grant_denied', $response->body['error']);
    }

    public function test_delete_requires_delete_grant_and_is_audited(): void
    {
        $auditPath = sys_get_temp_dir().'/connect-n2n-audit-'.bin2hex(random_bytes(4)).'.jsonl';
        $executor = $this->executor(new InMemoryRocketEntityAdapter(['page' => ['1' => ['id' => '1', 'title' => 'Hello']]]), $auditPath);
        $state = $this->state(['page' => ['delete']]);

        $response = $executor->execute(new BridgeRequest('rocket.entity.delete', 'page', ['id' => '1'], 'corr-1'), $state);

        self::assertSame(200, $response->status);
        self::assertFileExists($auditPath);
        self::assertStringContainsString('rocket.entity.delete', (string) file_get_contents($auditPath));
    }

    private function executor(InMemoryRocketEntityAdapter $adapter, ?string $auditPath = null): BridgeExecutor
    {
        return new BridgeExecutor(
            $this->discovery(),
            new EntityFieldPolicy,
            $adapter,
            $adapter,
            $adapter,
            $adapter,
            new AuditLogger($auditPath ?? sys_get_temp_dir().'/connect-n2n-audit-'.bin2hex(random_bytes(4)).'.jsonl'),
        );
    }

    private function discovery(): EntityDiscoveryService
    {
        return new EntityDiscoveryService(fn () => new FakeRocket(new FakeSpec([
            new FakeEiType('page', PageEntity::class, new FakeMask(
                'Pages',
                new FakeCollection([new FakeProp('title', new FakeStringNature('Title'))]),
                new FakeCollection([
                    new FakeCommand(new FakeOverviewCommand),
                    new FakeCommand(new FakeAddCommand),
                    new FakeCommand(new FakeEditCommand),
                    new FakeCommand(new FakeDeleteCommand),
                ]),
            )),
        ])), new RocketSchemaMapper);
    }

    private function state(array $grants): ConnectN2nState
    {
        return new ConnectN2nState(
            installationId: 'inst_123',
            publicId: 'public_123',
            serverSigningSecret: 'signing-secret',
            entityGrants: $grants,
        );
    }
}
