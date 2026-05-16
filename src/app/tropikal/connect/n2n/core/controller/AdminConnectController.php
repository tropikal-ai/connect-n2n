<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\controller;

use tropikal\connect\n2n\core\service\AdminAuthorizer;
use tropikal\connect\n2n\core\service\EntityDiscoveryService;
use tropikal\connect\n2n\core\service\InstallationService;
use tropikal\connect\n2n\core\service\PublicPayloadGuard;
use tropikal\connect\n2n\dto\BridgeResponse;

final readonly class AdminConnectController
{
    public function __construct(
        private AdminAuthorizer $authorizer,
        private InstallationService $installations,
        private EntityDiscoveryService $discovery,
    ) {}

    public function status(object $loginContext): BridgeResponse
    {
        $this->authorizer->requireAdmin($loginContext);
        $state = $this->installations->current();
        $entities = array_map(
            static fn ($entity): array => $entity->toArray(),
            array_values($this->discovery->discover()),
        );

        $payload = [
            'connection' => $state->safeStatus(),
            'entities' => $entities,
            'grants' => $state->entityGrants,
        ];
        PublicPayloadGuard::assertPublicPayload($payload);

        return BridgeResponse::ok($payload);
    }

    public function grant(object $loginContext, string $entityKey, string $grant, bool $enabled): BridgeResponse
    {
        $this->authorizer->requireAdmin($loginContext);
        $state = $this->installations->setGrant($entityKey, $grant, $enabled);

        return BridgeResponse::ok([
            'entity_key' => $entityKey,
            'grants' => $state->grantsFor($entityKey),
        ]);
    }

    public function revoke(object $loginContext): BridgeResponse
    {
        $this->authorizer->requireAdmin($loginContext);

        return BridgeResponse::ok($this->installations->revoke()->safeStatus());
    }
}
