<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\model;

use tropikal\connect\n2n\core\service\EntityDiscoveryService;
use tropikal\connect\n2n\dto\EntityManifest;
use tropikal\connect\n2n\dto\SiteIdentity;

final readonly class ConnectN2n
{
    public function __construct(
        private ConnectN2nConfig $config,
        private EntityDiscoveryService $discovery,
    ) {}

    public function manifest(ConnectN2nState $state, ?string $rocketVersion = null): array
    {
        return (new EntityManifest(
            new SiteIdentity($this->config->siteName, $this->config->baseUrl, rocketVersion: $rocketVersion),
            $this->discovery->discover(),
        ))->granted($state->entityGrants);
    }
}
