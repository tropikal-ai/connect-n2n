<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\application;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\application\SignedRequestGuard;
use tropikal\connect\n2n\domain\exception\SignatureException;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\tests\Support\InMemoryNonceStore;
use tropikal\connect\n2n\tests\Support\SampleResources;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * The end goal, at the library level: a TROPIKAL job, signing exactly as the
 * backend does, connects and reads/writes Article + Category through the
 * signature-guarded resource API — the same capability the Filament adapter
 * provides. The n2n HTTP transport + real ORM store are wired in Phase 3.
 */
final class ConnectJobFlowTest extends TestCase
{
    private const SECRET = 'bfs_installation_signing_secret';

    public function test_job_creates_a_category_then_writes_an_article_into_it(): void
    {
        [$api, $store] = SampleResources::api();
        $guard = new SignedRequestGuard(new InMemoryNonceStore);
        $installation = $this->installation();

        // 1. Job creates a Category via a signed POST.
        $categoryBody = (string) json_encode(['name' => 'Framework']);
        $this->assertSignedCall($guard, $installation, 'POST', '/resources/category', $categoryBody);
        $category = $api->create($installation, 'category', (array) json_decode($categoryBody, true));
        self::assertSame(201, $category->status);
        $categoryId = (int) $category->body['data']['id'];

        // 2. Job writes an Article into that Category via a signed POST.
        $articleBody = (string) json_encode([
            'title' => 'The canary is live', 'lead' => 'Minimal n2n.', 'online' => true, 'categoryId' => $categoryId,
        ]);
        $this->assertSignedCall($guard, $installation, 'POST', '/resources/article', $articleBody);
        $article = $api->create($installation, 'article', (array) json_decode($articleBody, true));
        self::assertSame(201, $article->status);
        self::assertSame($categoryId, $article->body['data']['categoryId']);

        // 3. Job reads the Articles back via a signed GET.
        $this->assertSignedCall($guard, $installation, 'GET', '/resources/article', '');
        $list = $api->list($installation, 'article', new ListQuery);
        self::assertSame(1, $list->body['meta']['total']);
        self::assertSame('The canary is live', $list->body['data'][0]['title']);
    }

    public function test_a_forged_request_never_reaches_the_api(): void
    {
        $guard = new SignedRequestGuard(new InMemoryNonceStore);
        $body = (string) json_encode(['name' => 'x']);
        $headers = SignedRequest::headers('bfs_attacker_secret', 'inst_1', 'POST', '/resources/category', null, $body);

        $this->expectException(SignatureException::class);
        $guard->verify($this->installation(), 'POST', '/resources/category', null, $body, $headers);
    }

    private function assertSignedCall(SignedRequestGuard $guard, Installation $installation, string $method, string $path, string $body): void
    {
        $headers = SignedRequest::headers(self::SECRET, 'inst_1', $method, $path, null, $body);
        $guard->verify($installation, $method, $path, null, $body, $headers);
    }

    private function installation(): Installation
    {
        return new Installation(
            installationId: 'inst_1',
            publicId: 'pub_1',
            signingSecret: self::SECRET,
            allowedResources: ['category', 'article'],
            resourcePermissions: [
                'category' => ['read', 'create', 'update'],
                'article' => ['read', 'create', 'update'],
            ],
        );
    }
}
