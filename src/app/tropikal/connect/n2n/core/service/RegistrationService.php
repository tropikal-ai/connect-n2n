<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\core\model\ConnectN2n;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\dto\SiteIdentity;

final readonly class RegistrationService
{
    public function __construct(
        private ConnectN2n $connect,
        private SiteIdentity $site,
    ) {}

    public function payload(ConnectN2nState $state, array $admin, ?string $rocketVersion = null): array
    {
        $payload = [
            'site' => $this->site->toArray(),
            'integration' => 'n2n-rocket',
            'admin' => array_intersect_key($admin, array_flip(['id', 'nick', 'email', 'is_super_admin'])),
            'manifest' => $this->connect->manifest($state, $rocketVersion),
        ];
        PublicPayloadGuard::assertPublicPayload($payload);

        return $payload;
    }
}
