<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\service\EntityDiscoveryService;
use tropikal\connect\n2n\core\service\RocketSchemaMapper;
use tropikal\connect\n2n\tests\Support\FakeAddCommand;
use tropikal\connect\n2n\tests\Support\FakeBoolNature;
use tropikal\connect\n2n\tests\Support\FakeCollection;
use tropikal\connect\n2n\tests\Support\FakeCommand;
use tropikal\connect\n2n\tests\Support\FakeDeleteCommand;
use tropikal\connect\n2n\tests\Support\FakeEditCommand;
use tropikal\connect\n2n\tests\Support\FakeEiType;
use tropikal\connect\n2n\tests\Support\FakeMask;
use tropikal\connect\n2n\tests\Support\FakeOnlineStatusNature;
use tropikal\connect\n2n\tests\Support\FakeOverviewCommand;
use tropikal\connect\n2n\tests\Support\FakePrivilegedNature;
use tropikal\connect\n2n\tests\Support\FakeProp;
use tropikal\connect\n2n\tests\Support\FakeRocket;
use tropikal\connect\n2n\tests\Support\FakeSpec;
use tropikal\connect\n2n\tests\Support\FakeStringNature;
use tropikal\connect\n2n\tests\Support\PageEntity;
use tropikal\connect\n2n\tests\Support\RocketUserEntity;

final class EntityDiscoveryTest extends TestCase
{
    public function test_discovers_safe_rocket_entities_from_spec(): void
    {
        $discovery = new EntityDiscoveryService(fn () => new FakeRocket(new FakeSpec([
            new FakeEiType('page', PageEntity::class, new FakeMask(
                'Pages',
                new FakeCollection([
                    new FakeProp('title', new FakeStringNature('Title')),
                    new FakeProp('published', new FakeBoolNature('Published')),
                    new FakeProp('password', new FakeStringNature('Password')),
                    new FakeProp('onlineStatus', new FakeOnlineStatusNature('Online')),
                    new FakeProp('internalNote', new FakePrivilegedNature('Internal')),
                ]),
                new FakeCollection([
                    new FakeCommand(new FakeOverviewCommand),
                    new FakeCommand(new FakeAddCommand),
                    new FakeCommand(new FakeEditCommand),
                    new FakeCommand(new FakeDeleteCommand),
                ]),
            )),
            new FakeEiType('rocket_user', RocketUserEntity::class, new FakeMask('Users', new FakeCollection([]), new FakeCollection([]))),
        ])), new RocketSchemaMapper);

        $entities = $discovery->discover();

        self::assertArrayHasKey('page', $entities);
        self::assertArrayNotHasKey('rocket_user', $entities);
        self::assertSame(['delete', 'get', 'list', 'create', 'update'], $this->sortedForAssertion($entities['page']->operations));
        self::assertArrayHasKey('title', $entities['page']->fields);
        self::assertArrayHasKey('published', $entities['page']->fields);
        self::assertArrayNotHasKey('password', $entities['page']->fields);
        self::assertArrayNotHasKey('internalNote', $entities['page']->fields);
        self::assertFalse($entities['page']->fields['onlineStatus']->writable);
    }

    public function test_returns_empty_schema_when_rocket_is_unavailable(): void
    {
        $discovery = new EntityDiscoveryService(fn () => null, new RocketSchemaMapper);

        self::assertSame([], $discovery->discover());
    }

    private function sortedForAssertion(array $operations): array
    {
        usort($operations, static fn (string $a, string $b): int => ['delete' => 0, 'get' => 1, 'list' => 2, 'create' => 3, 'update' => 4][$a] <=> ['delete' => 0, 'get' => 1, 'list' => 2, 'create' => 3, 'update' => 4][$b]);

        return $operations;
    }
}
