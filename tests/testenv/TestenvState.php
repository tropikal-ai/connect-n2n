<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\testenv;

use tropikal\connect\n2n\application\ConnectConfig;
use tropikal\connect\n2n\application\ConnectFlow;
use tropikal\connect\n2n\application\ResourceApi;
use tropikal\connect\n2n\application\SignedRequestGuard;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\service\CapabilityFactory;
use tropikal\connect\n2n\domain\service\FieldProjection;
use tropikal\connect\n2n\tests\Support\FakeAuthorizationServer;
use tropikal\connect\n2n\tests\Support\FakeControlPlane;
use tropikal\connect\n2n\tests\Support\InMemoryInstallationStore;
use tropikal\connect\n2n\tests\Support\InMemoryNonceStore;
use tropikal\connect\n2n\tests\Support\InMemoryResourceStore;
use tropikal\connect\n2n\tests\Support\NullAuditRecorder;
use tropikal\connect\n2n\tests\Support\SampleResources;
use tropikal\connect\n2n\web\ConnectComposition;
use tropikal\connect\n2n\web\QueryKeyAdminGate;

/**
 * The integration-test composition root: the same wiring a host application
 * performs in its controller subclasses, backed by the in-memory store so the
 * booted n2n pipeline (router → annotations → bind → api) is exercised without
 * a database. reset() gives each test a fresh, connected installation.
 */
final class TestenvState
{
    public const SECRET = 'bfs_testenv_signing_secret';

    public const INSTALLATION_ID = 'inst_testenv';

    public static InMemoryResourceStore $store;

    private static ?ConnectComposition $composition = null;

    public static function reset(): void
    {
        self::$store = new InMemoryResourceStore;
        $installations = new InMemoryInstallationStore;
        $installations->saveInstallation(new Installation(
            self::INSTALLATION_ID,
            'pub_testenv',
            self::SECRET,
            ['article', 'category'],
            ['article' => ['read', 'create', 'update', 'delete'], 'category' => ['read']],
        ));

        $catalog = SampleResources::catalog();
        $capabilities = new CapabilityFactory;

        self::$composition = new ConnectComposition(
            new ResourceApi($catalog, self::$store, new FieldProjection, $capabilities, new NullAuditRecorder),
            new SignedRequestGuard(new InMemoryNonceStore),
            $installations,
            new ConnectFlow(
                new ConnectConfig(
                    'https://site.test',
                    'https://id.test',
                    'https://app.test',
                    'https://site.test/connect/admin/callback',
                    'connect.install',
                    'https://app.test',
                    'testenv',
                    [],
                ),
                $installations,
                new FakeAuthorizationServer,
                new FakeControlPlane,
                $catalog,
                $capabilities,
            ),
            new QueryKeyAdminGate('testenv-admin-key-0123456789'),
        );
    }

    public static function composition(): ConnectComposition
    {
        if (self::$composition === null) {
            self::reset();
        }

        return self::$composition;
    }
}
