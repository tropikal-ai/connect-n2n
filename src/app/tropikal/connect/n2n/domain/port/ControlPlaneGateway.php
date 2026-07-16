<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

/** Outbound calls to the TROPIKAL control plane. */
interface ControlPlaneGateway
{
    /**
     * Registers this site's installation (Bearer-authenticated with the OAuth
     * access token) and returns the control plane's response body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerInstallation(array $payload, string $accessToken): array;
}
