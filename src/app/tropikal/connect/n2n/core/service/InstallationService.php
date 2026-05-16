<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\core\model\ConnectN2nState;

final readonly class InstallationService
{
    public function __construct(private SecretStore $store) {}

    public function current(): ConnectN2nState
    {
        return $this->store->load();
    }

    public function applyRegistration(array $response, string $createdByUserId): ConnectN2nState
    {
        $installationId = trim((string) ($response['installation_id'] ?? ''));
        $publicId = trim((string) ($response['public_id'] ?? $installationId));
        $secret = trim((string) ($response['server_signing_secret'] ?? ''));
        if ($installationId === '' || $publicId === '' || $secret === '') {
            throw new \InvalidArgumentException('Registration response must include installation_id, public_id, and server credentials.');
        }

        $now = gmdate(DATE_ATOM);
        $state = new ConnectN2nState(
            installationId: $installationId,
            publicId: $publicId,
            serverSigningSecret: $secret,
            keyId: is_string($response['key_id'] ?? null) ? $response['key_id'] : null,
            createdByUserId: $createdByUserId,
            createdAt: $now,
            updatedAt: $now,
            lastSuccessfulSyncAt: $now,
            accountLabel: is_string($response['account_label'] ?? null) ? $response['account_label'] : null,
        );

        $this->store->save($state);

        return $state;
    }

    public function setGrant(string $entityKey, string $grant, bool $enabled): ConnectN2nState
    {
        $state = $this->store->load()->withGrant($entityKey, $grant, $enabled);
        $this->store->save($state);

        return $state;
    }

    public function revoke(): ConnectN2nState
    {
        $state = $this->store->load()->revoked();
        $this->store->save($state);

        return $state;
    }
}
