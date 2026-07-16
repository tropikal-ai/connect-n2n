<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\oauth\PendingAuthorization;
use tropikal\connect\n2n\domain\port\InstallationStore;

final class InMemoryInstallationStore implements InstallationStore
{
    private Installation $installation;

    private ?PendingAuthorization $pending = null;

    public function __construct()
    {
        $this->installation = Installation::disconnected();
    }

    public function current(): Installation
    {
        return $this->installation;
    }

    public function loadInstallation(): Installation
    {
        return $this->installation;
    }

    public function saveInstallation(Installation $installation): void
    {
        $this->installation = $installation;
    }

    public function loadPending(): ?PendingAuthorization
    {
        return $this->pending;
    }

    public function savePending(?PendingAuthorization $pending): void
    {
        $this->pending = $pending;
    }
}
