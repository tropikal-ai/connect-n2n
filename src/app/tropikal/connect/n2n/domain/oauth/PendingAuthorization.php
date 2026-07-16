<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\oauth;

use TropikalAI\Connect\Domain\OAuth\OAuthState;

/**
 * The state persisted between the Connect click and the OAuth callback: the
 * registered client, the HASHED state (never the plain value), the PKCE
 * verifier, and the expiry. Stored encrypted at rest by the InstallationStore.
 */
final readonly class PendingAuthorization
{
    public function __construct(
        public string $clientId,
        public string $stateHash,
        public string $codeVerifier,
        public \DateTimeImmutable $expiresAt,
    ) {}

    public function matches(string $plainState, ?\DateTimeImmutable $now = null): bool
    {
        return OAuthState::valid($plainState, $this->stateHash, $this->expiresAt, $now);
    }
}
