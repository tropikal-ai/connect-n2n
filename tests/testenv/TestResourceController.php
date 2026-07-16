<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\testenv;

use n2n\core\container\N2nContext;
use tropikal\connect\n2n\web\ConnectComposition;
use tropikal\connect\n2n\web\ResourceController;

/** The host-application subclass under test, wired to the testenv composition. */
final class TestResourceController extends ResourceController
{
    protected function composition(N2nContext $n2nContext): ConnectComposition
    {
        return TestenvState::composition();
    }
}
