<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\integration;

use n2n\core\config\routing\ControllerDef;
use n2n\core\config\RoutingConfig;
use n2n\test\TestEnv;
use n2n\test\TestResponse;
use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\tests\testenv\TestenvState;
use tropikal\connect\n2n\tests\testenv\TestResourceController;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * The whole n2n-native vertical, driven through the framework itself: a booted
 * N2nContext routes real HTTP requests through the controller's path
 * annotations (AnnoPath + AnnoGet/AnnoPost/AnnoPut/AnnoDelete), the signature
 * guard, n2n-bind payload binding, and the resource API — exactly what a
 * TROPIKAL job hits in production, minus only the database.
 */
final class RoutedResourceApiTest extends TestCase
{
    protected function setUp(): void
    {
        TestenvState::reset();
        TestenvState::$store->seed('article', ['id' => 1, 'title' => 'First', 'lead' => 'Lead', 'online' => true]);
    }

    protected function tearDown(): void
    {
        TestEnv::resetN2nContext();
    }

    public function test_get_routes_to_list(): void
    {
        $response = $this->signed('GET', '/resources/article');

        $json = $response->parseJson();
        self::assertSame(200, $this->httpStatus($response));
        self::assertSame('First', $json['data'][0]['title']);
        self::assertSame(1, $json['meta']['total']);
    }

    public function test_get_with_id_routes_to_detail(): void
    {
        $response = $this->signed('GET', '/resources/article/1');

        self::assertSame(200, $this->httpStatus($response));
        self::assertSame('First', $response->parseJson()['data']['title']);
    }

    public function test_post_routes_to_create_and_binds_the_payload(): void
    {
        $response = $this->signed('POST', '/resources/article', [
            'title' => '  Routed and bound  ',
            'online' => true,
        ]);

        $json = $response->parseJson();
        self::assertSame(201, $this->httpStatus($response));
        self::assertSame('Routed and bound', $json['data']['title'], 'n2n-bind cleaned the string');
    }

    public function test_post_with_type_violation_is_rejected_by_bind(): void
    {
        $response = $this->signed('POST', '/resources/article', [
            'title' => 'ok',
            'online' => 'not-a-bool',
        ]);

        $json = $response->parseJson();
        self::assertSame(422, $this->httpStatus($response));
        self::assertSame('invalid_fields', $json['error']);
    }

    public function test_put_routes_to_update(): void
    {
        $response = $this->signed('PUT', '/resources/article/1', ['title' => 'Renamed']);

        self::assertSame(200, $this->httpStatus($response));
        self::assertSame('Renamed', $response->parseJson()['data']['title']);
    }

    public function test_delete_routes_to_remove(): void
    {
        $response = $this->signed('DELETE', '/resources/article/1');

        self::assertSame(200, $this->httpStatus($response));
        self::assertTrue($response->parseJson()['data']['deleted']);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        $request = TestEnv::http()->newRequest(routingConfig: $this->routing());
        $response = $request->get('/resources/article')->body('')->exec();

        self::assertSame(401, $this->httpStatus($response));
        self::assertSame('invalid_signature', $response->parseJson()['error']);
    }

    public function test_permission_denied_maps_to_403(): void
    {
        $response = $this->signed('POST', '/resources/category', ['name' => 'nope']);

        self::assertSame(403, $this->httpStatus($response));
        self::assertSame('permission_denied', $response->parseJson()['error']);
    }

    /** @param array<string, mixed>|null $body */
    private function signed(string $method, string $path, ?array $body = null): TestResponse
    {
        $bodyStr = $body === null ? '' : (string) json_encode($body);
        $headers = SignedRequest::headers(
            TestenvState::SECRET,
            TestenvState::INSTALLATION_ID,
            $method,
            $path,
            [],
            $bodyStr,
        );

        $request = TestEnv::http()->newRequest(routingConfig: $this->routing());
        $request = match ($method) {
            'GET' => $request->get($path),
            'POST' => $request->post($path),
            'PUT' => $request->put($path),
            'DELETE' => $request->delete($path),
        };

        foreach ($headers as $name => $value) {
            $request->header($name, (string) $value);
        }
        $request->body($bodyStr);

        return $request->exec();
    }

    private function routing(): RoutingConfig
    {
        return new RoutingConfig([
            new ControllerDef(TestResourceController::class, null, null, '/resources'),
        ]);
    }

    /** TestResponse does not expose the status code; read it off the wrapped Response. */
    private function httpStatus(TestResponse $response): int
    {
        $prop = new \ReflectionProperty(TestResponse::class, 'response');

        return $prop->getValue($response)->getStatus();
    }
}
