<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\domain\port\AuthorizationServerGateway;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;
use TropikalAI\Connect\Domain\Security\Base64Url;

/**
 * Fake authorization server that actually enforces PKCE: a code is bound to the
 * challenge it was issued for, and exchangeCode() recomputes S256(verifier) and
 * compares — so the flow test proves the verifier round-trip, not just wiring.
 */
final class FakeAuthorizationServer implements AuthorizationServerGateway
{
    public string $issuedClientId = 'client_fake_1';

    public string $issuedAccessToken = 'at_fake_access';

    /** @var array<string, string> code => expected challenge */
    private array $codes = [];

    public function registerClient(ClientRegistrationRequest $request): string
    {
        return $this->issuedClientId;
    }

    public function issueCode(string $challenge): string
    {
        $code = 'code_'.bin2hex(random_bytes(8));
        $this->codes[$code] = $challenge;

        return $code;
    }

    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet
    {
        $challenge = $this->codes[$code] ?? null;
        if ($challenge === null || ! hash_equals($challenge, Base64Url::encode(hash('sha256', $verifier, true)))) {
            throw new \RuntimeException('PKCE verification failed.');
        }

        return TokenSet::fromArray([
            'access_token' => $this->issuedAccessToken,
            'refresh_token' => 'rt_fake_refresh',
        ]);
    }
}
