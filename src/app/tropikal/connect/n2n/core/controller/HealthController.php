<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\controller;

use tropikal\connect\n2n\core\service\SecretStore;
use tropikal\connect\n2n\dto\BridgeResponse;

final readonly class HealthController
{
    public function __construct(private SecretStore $store) {}

    public function health(): BridgeResponse
    {
        return BridgeResponse::ok($this->store->load()->safeStatus());
    }
}
