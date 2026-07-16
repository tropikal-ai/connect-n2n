<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

use tropikal\connect\n2n\domain\installation\Installation;

/** Loads (and, for real registrations, persists) the site's Installation. */
interface InstallationRepository
{
    public function current(): Installation;
}
