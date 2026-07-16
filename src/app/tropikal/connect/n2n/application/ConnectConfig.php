<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

/**
 * Static configuration for the connect lifecycle. Paths default to the
 * TROPIKAL conventions; override per environment (e.g. a local fake control
 * plane in development).
 */
final readonly class ConnectConfig
{
    /** @param array<string, array<int, string>> $defaultPermissions grants applied on successful registration */
    public function __construct(
        public string $siteUrl,
        public string $authorizationServerUrl,
        public string $controlPlaneUrl,
        public string $redirectUri,
        public string $scopes,
        public string $resource,
        public string $clientName,
        public array $defaultPermissions,
        public string $authorizePath = '/oauth/authorize',
        public string $tokenPath = '/oauth/token',
        public string $registerClientPath = '/oauth/register',
        public string $registerInstallationPath = '/api/connect/installations',
        public ?string $configuredClientId = null,
        public int $timeoutSeconds = 10,
    ) {}
}
