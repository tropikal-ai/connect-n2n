<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\infrastructure;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\domain\exception\OAuthException;
use tropikal\connect\n2n\infrastructure\http\HttpJson;

/**
 * The OAuth/control-plane client must never send the authorization code, bearer
 * token, or refresh token over cleartext.
 */
final class HttpJsonTest extends TestCase
{
    public function test_rejects_plain_http_endpoint(): void
    {
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('https');

        (new HttpJson)->postForm('http://id.example.test/token', ['grant_type' => 'authorization_code']);
    }

    public function test_rejects_schemeless_endpoint(): void
    {
        $this->expectException(OAuthException::class);

        (new HttpJson)->postForm('id.example.test/token', ['grant_type' => 'authorization_code']);
    }

    public function test_loopback_http_passes_the_scheme_guard(): void
    {
        // http loopback clears the scheme guard; the request then fails at the
        // network layer (nothing is listening), proving the guard did not reject
        // it for scheme reasons.
        try {
            (new HttpJson(1))->postForm('http://127.0.0.1:9/token', ['x' => '1']);
            $this->addToAssertionCount(1);
        } catch (OAuthException $e) {
            $this->assertStringNotContainsString('https', $e->getMessage());
        }
    }
}
