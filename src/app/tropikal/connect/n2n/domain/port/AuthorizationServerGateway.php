<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;

/** Outbound calls to the TROPIKAL authorization server (OAuth 2.1 + PKCE). */
interface AuthorizationServerGateway
{
    /** Dynamic client registration (RFC 7591). Returns the client id. */
    public function registerClient(ClientRegistrationRequest $request): string;

    /** Exchanges an authorization code + PKCE verifier for tokens. */
    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet;
}
