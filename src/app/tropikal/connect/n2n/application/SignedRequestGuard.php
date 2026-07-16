<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

use tropikal\connect\n2n\domain\exception\SignatureException;
use tropikal\connect\n2n\domain\installation\Installation;
use TropikalAI\Connect\Application\Ports\NonceStore;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Exceptions\ConnectException;

/**
 * Verifies that an incoming request was signed by TROPIKAL for the connected
 * installation, delegating the HMAC/timestamp/nonce checks to the shared core
 * lib. Translates core failures into a domain {@see SignatureException}.
 */
final readonly class SignedRequestGuard
{
    public function __construct(
        private NonceStore $nonces,
        private int $toleranceSeconds = 300,
    ) {}

    /**
     * @param  array<string, string|array<int, string>>  $headers
     *
     * @throws SignatureException
     */
    public function verify(
        Installation $installation,
        string $method,
        string $path,
        array|string|null $query,
        string $body,
        array $headers,
    ): void {
        if (! $installation->isConnected() || $installation->installationId === null || $installation->signingSecret === null) {
            throw new SignatureException('Connect installation is not connected.');
        }

        try {
            (new SignedRequestVerifier($this->nonces, $this->toleranceSeconds))->verify(
                $installation->signingSecret,
                $installation->installationId,
                $method,
                $path,
                $query,
                $body,
                $headers,
            );
        } catch (ConnectException $exception) {
            throw new SignatureException($exception->getMessage(), previous: $exception);
        }
    }
}
