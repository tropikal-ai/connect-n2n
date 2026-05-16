<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\exception\InvalidSignatureException;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Domain\Security\SignedRequest;
use TropikalAI\Connect\Exceptions\ConnectException;

final readonly class SignatureVerifier
{
    public function __construct(
        private NonceStore $nonces,
        private int $toleranceSeconds = 300,
    ) {}

    public function verify(
        ConnectN2nState $state,
        string $method,
        string $path,
        array|string|null $query,
        string $body,
        array $headers,
    ): void {
        if (! $state->isConnected() || $state->installationId === null || $state->serverSigningSecret === null) {
            throw new InvalidSignatureException('Connect installation is not connected.');
        }

        try {
            (new SignedRequestVerifier($this->nonces, $this->toleranceSeconds))->verify(
                $state->serverSigningSecret,
                $state->installationId,
                $method,
                $path,
                $query,
                $body,
                $headers,
            );
        } catch (ConnectException $exception) {
            throw new InvalidSignatureException($exception->getMessage(), previous: $exception);
        }
    }

    public function installationIdFromHeaders(array $headers): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, SignedRequest::INSTALLATION_HEADER) === 0) {
                $header = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;

                return trim($header) !== '' ? trim($header) : null;
            }
        }

        return null;
    }
}
