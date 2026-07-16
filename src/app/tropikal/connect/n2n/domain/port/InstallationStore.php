<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\oauth\PendingAuthorization;

/**
 * Persists the connect lifecycle: the (eventually connected) Installation and
 * the transient PendingAuthorization between click and callback. Implementations
 * must store secrets encrypted at rest.
 */
interface InstallationStore extends InstallationRepository
{
    public function loadInstallation(): Installation;

    public function saveInstallation(Installation $installation): void;

    public function loadPending(): ?PendingAuthorization;

    public function savePending(?PendingAuthorization $pending): void;
}
