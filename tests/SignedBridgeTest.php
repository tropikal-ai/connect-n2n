<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\controller\BridgeController;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\core\service\AuditLogger;
use tropikal\connect\n2n\core\service\BridgeExecutor;
use tropikal\connect\n2n\core\service\EntityDiscoveryService;
use tropikal\connect\n2n\core\service\EntityFieldPolicy;
use tropikal\connect\n2n\core\service\NonceStore;
use tropikal\connect\n2n\core\service\RocketSchemaMapper;
use tropikal\connect\n2n\core\service\SecretStore;
use tropikal\connect\n2n\core\service\SignatureVerifier;
use tropikal\connect\n2n\tests\Support\FakeCollection;
use tropikal\connect\n2n\tests\Support\FakeCommand;
use tropikal\connect\n2n\tests\Support\FakeEiType;
use tropikal\connect\n2n\tests\Support\FakeMask;
use tropikal\connect\n2n\tests\Support\FakeOverviewCommand;
use tropikal\connect\n2n\tests\Support\FakeProp;
use tropikal\connect\n2n\tests\Support\FakeRocket;
use tropikal\connect\n2n\tests\Support\FakeSpec;
use tropikal\connect\n2n\tests\Support\FakeStringNature;
use tropikal\connect\n2n\tests\Support\InMemoryRocketEntityAdapter;
use tropikal\connect\n2n\tests\Support\PageEntity;
use TropikalAI\Connect\Domain\Security\SignedRequest;

final class SignedBridgeTest extends TestCase
{
    public function test_signed_bridge_accepts_valid_request(): void
    {
        $controller = $this->controller();
        $body = json_encode([
            'operation' => 'rocket.entity.get',
            'entity_key' => 'page',
            'payload' => ['id' => '1'],
        ], JSON_THROW_ON_ERROR);
        $headers = SignedRequest::headers('signing-secret', 'inst_123', 'POST', '/tropikal/connect-n2n/bridge', 'a=1', $body, time(), 'nonce-1');

        $response = $controller->bridge('POST', '/tropikal/connect-n2n/bridge', 'a=1', $body, $headers);

        self::assertSame(200, $response->status);
        self::assertSame('Hello', $response->body['data']['title']);
    }

    public function test_signed_bridge_rejects_bad_query_and_replayed_nonce(): void
    {
        $controller = $this->controller();
        $body = json_encode([
            'operation' => 'rocket.entity.get',
            'entity_key' => 'page',
            'payload' => ['id' => '1'],
        ], JSON_THROW_ON_ERROR);
        $headers = SignedRequest::headers('signing-secret', 'inst_123', 'POST', '/tropikal/connect-n2n/bridge', 'a=1', $body, time(), 'nonce-2');

        self::assertSame(401, $controller->bridge('POST', '/tropikal/connect-n2n/bridge', 'a=2', $body, $headers)->status);
        self::assertSame(200, $controller->bridge('POST', '/tropikal/connect-n2n/bridge', 'a=1', $body, $headers)->status);
        self::assertSame(401, $controller->bridge('POST', '/tropikal/connect-n2n/bridge', 'a=1', $body, $headers)->status);
    }

    public function test_signed_bridge_rejects_expired_timestamp_and_bad_body_hash(): void
    {
        $controller = $this->controller();
        $body = json_encode([
            'operation' => 'rocket.entity.get',
            'entity_key' => 'page',
            'payload' => ['id' => '1'],
        ], JSON_THROW_ON_ERROR);

        $expiredHeaders = SignedRequest::headers('signing-secret', 'inst_123', 'POST', '/tropikal/connect-n2n/bridge', '', $body, time() - 1000, 'nonce-3');
        self::assertSame(401, $controller->bridge('POST', '/tropikal/connect-n2n/bridge', '', $body, $expiredHeaders)->status);

        $headers = SignedRequest::headers('signing-secret', 'inst_123', 'POST', '/tropikal/connect-n2n/bridge', '', $body, time(), 'nonce-4');
        self::assertSame(401, $controller->bridge('POST', '/tropikal/connect-n2n/bridge', '', '{"changed":true}', $headers)->status);
    }

    private function controller(): BridgeController
    {
        $adapter = new InMemoryRocketEntityAdapter(['page' => ['1' => ['id' => '1', 'title' => 'Hello']]]);
        $discovery = new EntityDiscoveryService(fn () => new FakeRocket(new FakeSpec([
            new FakeEiType('page', PageEntity::class, new FakeMask(
                'Pages',
                new FakeCollection([new FakeProp('title', new FakeStringNature('Title'))]),
                new FakeCollection([new FakeCommand(new FakeOverviewCommand)]),
            )),
        ])), new RocketSchemaMapper);

        return new BridgeController(
            new FixedSecretStore(new ConnectN2nState(
                installationId: 'inst_123',
                publicId: 'public_123',
                serverSigningSecret: 'signing-secret',
                entityGrants: ['page' => ['read']],
            )),
            new SignatureVerifier(new NonceStore(sys_get_temp_dir().'/connect-n2n-nonces-'.bin2hex(random_bytes(4))), 300),
            new BridgeExecutor(
                $discovery,
                new EntityFieldPolicy,
                $adapter,
                $adapter,
                $adapter,
                $adapter,
                new AuditLogger(sys_get_temp_dir().'/connect-n2n-audit-'.bin2hex(random_bytes(4)).'.jsonl'),
            ),
        );
    }
}

final readonly class FixedSecretStore implements SecretStore
{
    public function __construct(private ConnectN2nState $state) {}

    public function load(): ConnectN2nState
    {
        return $this->state;
    }

    public function save(ConnectN2nState $state): void {}

    public function delete(): void {}
}
