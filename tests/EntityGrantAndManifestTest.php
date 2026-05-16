<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\dto\EntityDescriptor;
use tropikal\connect\n2n\dto\EntityManifest;
use tropikal\connect\n2n\dto\FieldDescriptor;
use tropikal\connect\n2n\dto\SiteIdentity;

final class EntityGrantAndManifestTest extends TestCase
{
    public function test_empty_grants_expose_no_entities(): void
    {
        $manifest = new EntityManifest($this->site(), ['page' => $this->entity()]);

        self::assertSame([], $manifest->granted([])['entities']);
    }

    public function test_read_write_delete_are_independent_grants(): void
    {
        $manifest = new EntityManifest($this->site(), ['page' => $this->entity()]);
        $state = ConnectN2nState::disconnected()
            ->withGrant('page', 'write', true);

        $entities = $manifest->granted($state->entityGrants)['entities'];

        self::assertCount(1, $entities);
        self::assertSame(['create', 'update'], $entities[0]['operations']);
        self::assertFalse($entities[0]['access']['read']);
        self::assertTrue($entities[0]['access']['write']);
        self::assertFalse($entities[0]['access']['delete']);

        $state = $state->withGrant('page', 'delete', true);
        $entities = $manifest->granted($state->entityGrants)['entities'];
        self::assertContains('delete', $entities[0]['operations']);
    }

    private function site(): SiteIdentity
    {
        return new SiteIdentity('Example', 'https://example.com');
    }

    private function entity(): EntityDescriptor
    {
        return new EntityDescriptor('page', 'Pages', true, true, true, [
            'title' => new FieldDescriptor('title', 'Title', writable: true, required: true),
        ], ['list', 'get', 'create', 'update', 'delete']);
    }
}
