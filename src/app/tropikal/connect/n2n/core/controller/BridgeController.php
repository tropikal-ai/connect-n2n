<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\controller;

use tropikal\connect\n2n\core\service\BridgeExecutor;
use tropikal\connect\n2n\core\service\SecretStore;
use tropikal\connect\n2n\core\service\SignatureVerifier;
use tropikal\connect\n2n\dto\BridgeRequest;
use tropikal\connect\n2n\dto\BridgeResponse;
use tropikal\connect\n2n\exception\InvalidSignatureException;

final readonly class BridgeController
{
    public function __construct(
        private SecretStore $store,
        private SignatureVerifier $verifier,
        private BridgeExecutor $executor,
    ) {}

    public function bridge(string $method, string $path, array|string|null $query, string $body, array $headers): BridgeResponse
    {
        $state = $this->store->load();
        try {
            $this->verifier->verify($state, $method, $path, $query, $body, $headers);
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \InvalidArgumentException('Bridge request body must be a JSON object.');
            }

            return $this->executor->execute(BridgeRequest::fromArray($payload), $state);
        } catch (InvalidSignatureException) {
            return BridgeResponse::error('invalid_signature', 'Invalid connect signature.', 401);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return BridgeResponse::error('validation_error', $exception->getMessage(), 422);
        }
    }
}
