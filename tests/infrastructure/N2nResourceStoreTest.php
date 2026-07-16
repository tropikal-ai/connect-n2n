<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\infrastructure;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\infrastructure\orm\N2nResourceStore;
use tropikal\connect\n2n\infrastructure\orm\OrmResourceBinding;
use tropikal\connect\n2n\tests\Support\FakeOrmSession;
use tropikal\connect\n2n\tests\Support\SampleArticle;
use tropikal\connect\n2n\tests\Support\SampleCategory;
use tropikal\connect\n2n\tests\Support\SampleResources;

/**
 * The n2n ORM-backed ResourceStore, driven by a fake OrmSession + the canary's
 * Article/Category entity shapes — proving the store maps resource records to
 * getters/setters and resolves the categoryId relation, with no n2n runtime.
 */
final class N2nResourceStoreTest extends TestCase
{
    public function test_creates_and_reads_a_category(): void
    {
        [$store] = $this->store();
        $spec = SampleResources::category();

        $record = $store->create($spec, ['name' => 'Framework']);

        self::assertSame('Framework', $record['name']);
        self::assertNotNull($record['id']);
        self::assertEquals($record, $store->get($spec, (string) $record['id']));
    }

    public function test_writes_and_reads_the_article_category_relation(): void
    {
        [$store, $session] = $this->store();
        $session->seed(new SampleCategory, 7);
        $spec = SampleResources::article();

        $record = $store->create($spec, ['title' => 'X', 'lead' => 'y', 'online' => true, 'categoryId' => 7]);

        self::assertSame(7, $record['categoryId']);
        self::assertTrue($record['online']);
    }

    public function test_list_returns_a_page_and_a_total(): void
    {
        [$store] = $this->store();
        $spec = SampleResources::category();
        $store->create($spec, ['name' => 'One']);
        $store->create($spec, ['name' => 'Two']);

        $page = $store->list($spec, new ListQuery(perPage: 1));

        self::assertSame(2, $page['total']);
        self::assertCount(1, $page['records']);
    }

    public function test_update_moves_the_relation_and_delete_removes(): void
    {
        [$store, $session] = $this->store();
        $session->seed(new SampleCategory, 1);
        $session->seed(new SampleCategory, 2);
        $spec = SampleResources::article();
        $created = $store->create($spec, ['title' => 'A', 'lead' => 'a', 'categoryId' => 1]);

        $updated = $store->update($spec, (string) $created['id'], ['categoryId' => 2]);
        self::assertSame(2, $updated['categoryId']);

        self::assertTrue($store->delete($spec, (string) $created['id']));
        self::assertNull($store->get($spec, (string) $created['id']));
    }

    /** @return array{0: N2nResourceStore, 1: FakeOrmSession} */
    private function store(): array
    {
        $session = new FakeOrmSession;
        $store = new N2nResourceStore($session, [
            'category' => new OrmResourceBinding(SampleCategory::class),
            'article' => new OrmResourceBinding(SampleArticle::class, ['categoryId' => SampleCategory::class]),
        ]);

        return [$store, $session];
    }
}
