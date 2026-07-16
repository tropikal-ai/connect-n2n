<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\application;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\application\ConnectConfig;
use tropikal\connect\n2n\application\ConnectFlow;
use tropikal\connect\n2n\domain\exception\OAuthException;
use tropikal\connect\n2n\domain\service\CapabilityFactory;
use tropikal\connect\n2n\tests\Support\FakeAuthorizationServer;
use tropikal\connect\n2n\tests\Support\FakeControlPlane;
use tropikal\connect\n2n\tests\Support\InMemoryInstallationStore;
use tropikal\connect\n2n\tests\Support\SampleResources;

/**
 * The one-click connect lifecycle, exactly as the Filament adapter does it:
 * begin() registers an OAuth client (PKCE, hashed state) and yields the
 * authorization URL; complete() validates the callback, exchanges the code for
 * tokens, registers the installation on the control plane with the Bearer
 * token, and stores the returned server signing key. Written first (TDD).
 */
final class ConnectFlowTest extends TestCase
{
    public function test_begin_registers_a_client_and_builds_the_authorization_url(): void
    {
        [$flow, , $authServer] = $this->flow();

        $url = $flow->begin();

        $q = $this->query($url);
        self::assertStringStartsWith('https://auth.example.test/oauth/authorize?', $url);
        self::assertSame('code', $q['response_type']);
        self::assertSame($authServer->issuedClientId, $q['client_id'], 'client came from dynamic registration');
        self::assertSame('https://canary.example.test/connect/admin/callback', $q['redirect_uri']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertNotEmpty($q['state']);
        self::assertNotEmpty($q['code_challenge']);
    }

    public function test_complete_exchanges_the_code_and_stores_the_signing_key(): void
    {
        [$flow, $store, $authServer, $controlPlane] = $this->flow();
        $url = $flow->begin();
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        $installation = $flow->complete($state, $code, 'https://canary.example.test/connect/admin/callback?state='.$state.'&code='.$code);

        self::assertTrue($installation->isConnected());
        self::assertSame($controlPlane->issuedSigningKey, $installation->signingSecret);
        self::assertSame($controlPlane->issuedInstallationId, $installation->installationId);
        self::assertSame(['category', 'article'], $installation->allowedResources);
        // persisted for the next request
        self::assertTrue($store->loadInstallation()->isConnected());
        self::assertNull($store->loadPending(), 'pending authorization is cleared');
        // registration payload carried the capability manifest + bearer token
        self::assertSame($authServer->issuedAccessToken, $controlPlane->seenAccessToken);
        self::assertSame('https://canary.example.test', $controlPlane->seenPayload['site_url']);
        self::assertNotEmpty($controlPlane->seenPayload['resources']);
    }

    public function test_complete_rejects_a_wrong_state(): void
    {
        [$flow] = $this->flow();
        $flow->begin();

        $this->expectException(OAuthException::class);
        $flow->complete('forged-state', 'any-code', 'https://canary.example.test/connect/admin/callback');
    }

    public function test_complete_rejects_a_mismatched_redirect_url(): void
    {
        [$flow, , $authServer] = $this->flow();
        $url = $flow->begin();
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        $this->expectException(OAuthException::class);
        $flow->complete($state, $code, 'https://evil.example.test/connect/admin/callback');
    }

    public function test_complete_fails_closed_when_control_plane_returns_no_key(): void
    {
        [$flow, $store, $authServer, $controlPlane] = $this->flow();
        $controlPlane->response = ['account' => ['label' => 'x']]; // no server_signing_key
        $url = $flow->begin();
        $state = $this->query($url)['state'];
        $code = $authServer->issueCode($this->query($url)['code_challenge']);

        try {
            $flow->complete($state, $code, 'https://canary.example.test/connect/admin/callback?x=1');
            self::fail('expected OAuthException');
        } catch (OAuthException) {
        }

        self::assertFalse($store->loadInstallation()->isConnected(), 'no half-connected state persisted');
    }

    /** @return array{0: ConnectFlow, 1: InMemoryInstallationStore, 2: FakeAuthorizationServer, 3: FakeControlPlane} */
    private function flow(): array
    {
        $store = new InMemoryInstallationStore;
        $authServer = new FakeAuthorizationServer;
        $controlPlane = new FakeControlPlane;

        $flow = new ConnectFlow(
            new ConnectConfig(
                siteUrl: 'https://canary.example.test',
                authorizationServerUrl: 'https://auth.example.test',
                controlPlaneUrl: 'https://ops.example.test',
                redirectUri: 'https://canary.example.test/connect/admin/callback',
                scopes: 'connect.install',
                resource: 'https://ops.example.test',
                clientName: 'Canary',
                defaultPermissions: [
                    'category' => ['read', 'create', 'update', 'delete'],
                    'article' => ['read', 'create', 'update', 'delete'],
                ],
            ),
            $store,
            $authServer,
            $controlPlane,
            SampleResources::catalog(),
            new CapabilityFactory('n2n'),
        );

        return [$flow, $store, $authServer, $controlPlane];
    }

    /** @return array<string, string> */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return array_map(strval(...), $q);
    }
}
