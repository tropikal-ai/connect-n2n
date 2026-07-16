<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\application;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\tests\Support\SampleResources;

/**
 * Drives the Filament-modeled resource API: a connected job lists/gets/creates/
 * updates/deletes Article + Category, gated by per-resource grants and the field
 * policy. This is the behavioural contract for the connect core.
 */
final class ResourceApiTest extends TestCase
{
    public function test_create_grant_creates_an_article_in_a_category(): void
    {
        [$api, $store] = SampleResources::api();
        $store->seed('category', ['id' => 1, 'name' => 'Framework']);

        $result = $api->create($this->installation(['article' => ['read', 'create', 'update']]), 'article', [
            'title' => 'The canary is live', 'lead' => 'Minimal n2n.', 'online' => true, 'categoryId' => 1,
        ]);

        self::assertSame(201, $result->status);
        self::assertSame('The canary is live', $result->body['data']['title']);
        self::assertSame(1, $result->body['data']['categoryId']);
    }

    public function test_read_grant_lists_articles_with_pagination_meta(): void
    {
        [$api, $store] = SampleResources::api();
        $store->seed('article', ['id' => 1, 'title' => 'A', 'lead' => 'a', 'online' => true, 'categoryId' => null]);
        $store->seed('article', ['id' => 2, 'title' => 'B', 'lead' => 'b', 'online' => true, 'categoryId' => null]);

        $result = $api->list($this->installation(['article' => ['read']]), 'article', new ListQuery(perPage: 1));

        self::assertSame(200, $result->status);
        self::assertCount(1, $result->body['data']);
        self::assertSame(2, $result->body['meta']['total']);
        self::assertSame(2, $result->body['meta']['last_page']);
        self::assertSame(1, $result->body['meta']['per_page']);
    }

    public function test_get_and_delete_round_trip(): void
    {
        [$api, $store] = SampleResources::api();
        $store->seed('category', ['id' => 5, 'name' => 'Ops']);
        $installation = $this->installation(['category' => ['read', 'delete']]);

        $got = $api->get($installation, 'category', '5');
        self::assertSame(200, $got->status);
        self::assertSame('Ops', $got->body['data']['name']);

        $deleted = $api->delete($installation, 'category', '5');
        self::assertSame(200, $deleted->status);
        self::assertSame(['id' => '5', 'deleted' => true], $deleted->body['data']);
        self::assertSame(404, $api->get($installation, 'category', '5')->status);
    }

    public function test_read_grant_cannot_create(): void
    {
        [$api] = SampleResources::api();

        $result = $api->create($this->installation(['article' => ['read']]), 'article', ['title' => 'x', 'lead' => 'y']);

        self::assertSame(403, $result->status);
        self::assertSame('permission_denied', $result->body['error']);
    }

    public function test_unknown_write_field_is_rejected(): void
    {
        [$api] = SampleResources::api();

        $result = $api->create($this->installation(['category' => ['read', 'create', 'update']]), 'category', [
            'name' => 'Ok', 'sneaky' => true,
        ]);

        self::assertSame(422, $result->status);
        self::assertSame('unknown_fields', $result->body['error']);
    }

    public function test_resource_not_in_allowed_list_is_forbidden(): void
    {
        [$api] = SampleResources::api();
        // installation has no grants at all
        $result = $api->list(Installation::disconnected(), 'article', new ListQuery);

        self::assertSame(403, $result->status);
    }

    public function test_schema_exposes_capabilities_for_granted_resources_only(): void
    {
        [$api] = SampleResources::api();

        $result = $api->schema($this->installation(['category' => ['read', 'create', 'update', 'delete']]));

        self::assertSame(200, $result->status);
        $keys = array_column($result->body['resources'], 'resource_key');
        self::assertContains('category', $keys);
        self::assertNotContains('article', $keys, 'ungranted resources are not exposed');

        $category = $result->body['resources'][array_search('category', $keys, true)];
        $operations = array_column($category['operations'], 'operation');
        self::assertEqualsCanonicalizing(['list', 'get', 'create', 'update', 'delete'], $operations);
        $delete = $category['operations'][array_search('delete', $operations, true)];
        self::assertSame('destructive', $delete['risk_level']);
    }

    /** @param array<string, array<int, string>> $permissions */
    private function installation(array $permissions): Installation
    {
        return new Installation(
            installationId: 'inst_1',
            publicId: 'pub_1',
            signingSecret: 'bfs_secret',
            allowedResources: array_keys($permissions),
            resourcePermissions: $permissions,
        );
    }
}
