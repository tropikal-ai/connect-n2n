<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\application;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\application\SignedRequestGuard;
use tropikal\connect\n2n\domain\exception\SignatureException;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\tests\Support\InMemoryNonceStore;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * The signature gate: a TROPIKAL job signs its request with the installation's
 * shared secret (via the core lib), and the guard accepts only genuine,
 * unexpired, un-replayed requests for the connected installation.
 */
final class SignedRequestGuardTest extends TestCase
{
    private const SECRET = 'bfs_super_secret_key_value';

    public function test_valid_signed_request_passes(): void
    {
        $headers = SignedRequest::headers(self::SECRET, 'inst_1', 'GET', '/resources/article', null, '');

        $this->guard()->verify($this->installation(), 'GET', '/resources/article', null, '', $headers);

        $this->expectNotToPerformAssertions();
    }

    public function test_tampered_body_is_rejected(): void
    {
        $headers = SignedRequest::headers(self::SECRET, 'inst_1', 'POST', '/resources/article', null, '{"title":"real"}');

        $this->expectException(SignatureException::class);
        $this->guard()->verify($this->installation(), 'POST', '/resources/article', null, '{"title":"hacked"}', $headers);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $headers = SignedRequest::headers('bfs_attacker_secret', 'inst_1', 'GET', '/resources/article', null, '');

        $this->expectException(SignatureException::class);
        $this->guard()->verify($this->installation(), 'GET', '/resources/article', null, '', $headers);
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $guard = $this->guard();
        $headers = SignedRequest::headers(self::SECRET, 'inst_1', 'GET', '/resources/article', null, '');
        $guard->verify($this->installation(), 'GET', '/resources/article', null, '', $headers);

        $this->expectException(SignatureException::class);
        $guard->verify($this->installation(), 'GET', '/resources/article', null, '', $headers);
    }

    public function test_disconnected_installation_is_rejected(): void
    {
        $headers = SignedRequest::headers(self::SECRET, 'inst_1', 'GET', '/resources/article', null, '');

        $this->expectException(SignatureException::class);
        $this->guard()->verify(Installation::disconnected(), 'GET', '/resources/article', null, '', $headers);
    }

    private function guard(): SignedRequestGuard
    {
        return new SignedRequestGuard(new InMemoryNonceStore);
    }

    private function installation(): Installation
    {
        return new Installation('inst_1', 'pub_1', self::SECRET, ['article'], ['article' => ['read']]);
    }
}
