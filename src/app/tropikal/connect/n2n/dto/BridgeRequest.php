<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

final readonly class BridgeRequest
{
    public function __construct(
        public string $operation,
        public string $entityKey,
        public array $payload = [],
        public ?string $correlationId = null,
    ) {}

    public static function fromArray(array $body): self
    {
        $operation = trim((string) ($body['operation'] ?? ''));
        $entityKey = trim((string) ($body['entity_key'] ?? ''));
        if ($operation === '' || $entityKey === '') {
            throw new \InvalidArgumentException('Bridge request requires operation and entity_key.');
        }

        $payload = $body['payload'] ?? [];
        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Bridge request payload must be an object.');
        }

        $correlationId = $body['correlation_id'] ?? null;

        return new self(
            $operation,
            $entityKey,
            $payload,
            is_string($correlationId) && $correlationId !== '' ? $correlationId : null,
        );
    }
}
