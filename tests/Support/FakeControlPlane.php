<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\domain\port\ControlPlaneGateway;

/** Captures the registration call and returns a configurable response. */
final class FakeControlPlane implements ControlPlaneGateway
{
    public string $issuedSigningKey = 'bfs_fake_server_signing_key';

    public string $issuedInstallationId = 'inst_fake_1';

    /** @var array<string, mixed>|null override the default response */
    public ?array $response = null;

    /** @var array<string, mixed> */
    public array $seenPayload = [];

    public string $seenAccessToken = '';

    public function registerInstallation(array $payload, string $accessToken): array
    {
        $this->seenPayload = $payload;
        $this->seenAccessToken = $accessToken;

        return $this->response ?? [
            'installation_id' => $this->issuedInstallationId,
            'server_signing_key' => $this->issuedSigningKey,
            'account' => ['label' => 'Fake Account'],
        ];
    }
}
