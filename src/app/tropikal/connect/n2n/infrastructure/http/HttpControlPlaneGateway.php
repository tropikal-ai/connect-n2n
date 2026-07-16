<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\http;

use tropikal\connect\n2n\application\ConnectConfig;
use tropikal\connect\n2n\domain\port\ControlPlaneGateway;

/** Registers the installation with the TROPIKAL control plane over HTTPS. */
final readonly class HttpControlPlaneGateway implements ControlPlaneGateway
{
    public function __construct(
        private ConnectConfig $config,
        private HttpJson $http,
    ) {}

    public function registerInstallation(array $payload, string $accessToken): array
    {
        return $this->http->postJson(
            rtrim($this->config->controlPlaneUrl, '/').$this->config->registerInstallationPath,
            $payload,
            ['Authorization: Bearer '.$accessToken],
        );
    }
}
