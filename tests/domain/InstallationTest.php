<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\domain;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\domain\grant\Permission;
use tropikal\connect\n2n\domain\installation\Installation;

final class InstallationTest extends TestCase
{
    public function test_enabling_a_grant_exposes_the_resource(): void
    {
        $installation = $this->connected()->withGrant('article', Permission::Read, true);

        self::assertTrue($installation->allowsResource('article'));
        self::assertTrue($installation->allows('article', Permission::Read));
        self::assertFalse($installation->allows('article', Permission::Delete));
    }

    public function test_dropping_the_last_grant_unexposes_the_resource(): void
    {
        $installation = $this->connected()
            ->withGrant('article', Permission::Read, true)
            ->withGrant('article', Permission::Create, true)
            ->withGrant('article', Permission::Read, false)
            ->withGrant('article', Permission::Create, false);

        self::assertFalse($installation->allowsResource('article'), 'no permissions left => not exposed');
        self::assertSame([], $installation->permissionsFor('article'));
        self::assertSame([], $installation->resourcePermissions, 'empty grant entries are dropped');
    }

    public function test_dropping_one_of_two_grants_keeps_the_resource_exposed(): void
    {
        $installation = $this->connected()
            ->withGrant('article', Permission::Read, true)
            ->withGrant('article', Permission::Delete, true)
            ->withGrant('article', Permission::Delete, false);

        self::assertTrue($installation->allowsResource('article'));
        self::assertSame(['read'], $installation->permissionsFor('article'));
    }

    private function connected(): Installation
    {
        return new Installation('inst_1', 'pub_1', 'bfs_secret');
    }
}
