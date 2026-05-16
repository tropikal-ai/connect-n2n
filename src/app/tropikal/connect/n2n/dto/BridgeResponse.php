<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class BridgeResponse
{
    private function __construct(
        public int $status,
        public array $body,
    ) {
        PublicPayloadGuard::assertPublicPayload($this->body);
    }

    public static function ok(array $data): self
    {
        return new self(200, ['data' => $data]);
    }

    public static function created(array $data): self
    {
        return new self(201, ['data' => $data]);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): self
    {
        return new self($status, array_filter([
            'error' => $code,
            'message' => $message,
            'details' => $details,
        ], static fn (mixed $value): bool => $value !== []));
    }
}
