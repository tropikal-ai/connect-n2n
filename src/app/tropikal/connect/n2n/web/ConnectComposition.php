<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use tropikal\connect\n2n\application\ConnectFlow;
use tropikal\connect\n2n\application\ResourceApi;
use tropikal\connect\n2n\application\SignedRequestGuard;
use tropikal\connect\n2n\domain\port\InstallationStore;

/**
 * Everything the connect controllers need, assembled once by the host
 * application's composition root (see ConnectControllerBase::composition()).
 */
final readonly class ConnectComposition
{
    public function __construct(
        public ResourceApi $api,
        public SignedRequestGuard $guard,
        public InstallationStore $installations,
        public ConnectFlow $flow,
        public AdminGate $adminGate,
    ) {}
}
