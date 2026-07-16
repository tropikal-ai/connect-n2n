<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

use tropikal\connect\n2n\domain\exception\OAuthException;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\oauth\PendingAuthorization;
use tropikal\connect\n2n\domain\port\AuthorizationServerGateway;
use tropikal\connect\n2n\domain\port\ControlPlaneGateway;
use tropikal\connect\n2n\domain\port\InstallationStore;
use tropikal\connect\n2n\domain\port\ResourceCatalog;
use tropikal\connect\n2n\domain\service\CapabilityFactory;
use TropikalAI\Connect\Domain\OAuth\AuthorizationRequest;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\OAuthState;
use TropikalAI\Connect\Domain\OAuth\PkcePair;
use TropikalAI\Connect\Domain\OAuth\RedirectUri;

/**
 * The one-click connect lifecycle, mirroring the Filament adapter:
 *
 *   begin()    — ensure an OAuth client (dynamic registration if none is
 *                configured), generate PKCE + hashed state, persist the pending
 *                authorization, and return the authorization URL to redirect to.
 *   complete() — validate the callback (state, expiry, redirect URI), exchange
 *                the code + verifier for tokens, register the installation on
 *                the control plane (Bearer access token, capability manifest in
 *                the payload), and persist the returned server signing key.
 *
 * Fails closed: no partial connected state is ever stored.
 */
final readonly class ConnectFlow
{
    public function __construct(
        private ConnectConfig $config,
        private InstallationStore $store,
        private AuthorizationServerGateway $authServer,
        private ControlPlaneGateway $controlPlane,
        private ResourceCatalog $catalog,
        private CapabilityFactory $capabilities,
    ) {}

    /** @return string the authorization URL the browser must be redirected to */
    public function begin(): string
    {
        $clientId = $this->config->configuredClientId;
        if ($clientId === null) {
            $clientId = $this->store->loadPending()?->clientId;
        }
        $clientId ??= $this->authServer->registerClient(new ClientRegistrationRequest(
            $this->config->clientName,
            [$this->config->redirectUri],
            $this->config->scopes,
            $this->config->resource,
            $this->config->siteUrl,
            '',
        ));

        $state = OAuthState::generate();
        $pkce = PkcePair::generate();

        $this->store->savePending(new PendingAuthorization($clientId, $state->hash, $pkce->verifier, $state->expiresAt));

        return (new AuthorizationRequest(
            rtrim($this->config->authorizationServerUrl, '/').$this->config->authorizePath,
            $clientId,
            $this->config->redirectUri,
            $this->config->scopes,
            $this->config->resource,
            $state->plain,
            $pkce,
        ))->url();
    }

    public function complete(string $state, string $code, string $callbackUrl): Installation
    {
        if ($state === '' || $code === '') {
            throw new OAuthException('OAuth callback is missing state or code.');
        }
        if (! (new RedirectUri($this->config->redirectUri))->matches($this->withoutQuery($callbackUrl))) {
            throw new OAuthException('OAuth callback URL does not match the configured redirect URI.');
        }

        $pending = $this->store->loadPending();
        if ($pending === null || ! $pending->matches($state)) {
            throw new OAuthException('OAuth state is invalid or has expired.');
        }

        $tokens = $this->authServer->exchangeCode(
            $pending->clientId,
            $this->config->redirectUri,
            $code,
            $pending->codeVerifier,
            $this->config->resource,
        );

        $body = $this->controlPlane->registerInstallation($this->registrationPayload(), $tokens->accessToken);

        $signingKey = trim((string) ($body['server_signing_key'] ?? ''));
        $installationId = trim((string) ($body['installation_id'] ?? $body['installation_public_id'] ?? ''));
        if ($signingKey === '' || $installationId === '') {
            throw new OAuthException('The control plane response did not include server credentials.');
        }

        $installation = new Installation(
            installationId: $installationId,
            publicId: trim((string) ($body['installation_public_id'] ?? $installationId)),
            signingSecret: $signingKey,
            allowedResources: array_keys($this->config->defaultPermissions),
            resourcePermissions: $this->config->defaultPermissions,
        );

        $this->store->saveInstallation($installation);
        $this->store->savePending(null);

        return $installation;
    }

    /** RedirectUri::matches is byte-exact against the query-less URL (Filament passes Laravel's $request->url()). */
    private function withoutQuery(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');
    }

    /** @return array<string, mixed> */
    private function registrationPayload(): array
    {
        $resources = [];
        foreach ($this->catalog->all() as $slug => $spec) {
            $permissions = $this->config->defaultPermissions[$slug] ?? [];
            if ($permissions === []) {
                continue;
            }
            $resources[$slug] = $this->capabilities->forResource($spec, $permissions)->toArray();
        }

        return [
            'site_url' => $this->config->siteUrl,
            'api_base_url' => rtrim($this->config->siteUrl, '/').'/connect',
            'resources' => $resources,
        ];
    }
}
