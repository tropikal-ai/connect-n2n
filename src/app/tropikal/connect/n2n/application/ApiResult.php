<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

/** An HTTP-agnostic result: a status code plus a JSON-serialisable body. */
final readonly class ApiResult
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public int $status,
        public array $body,
    ) {}

    /** @param array<string, mixed> $body */
    public static function ok(array $body): self
    {
        return new self(200, $body);
    }

    /** @param array<string, mixed> $body */
    public static function created(array $body): self
    {
        return new self(201, $body);
    }

    /** @param array<string, mixed> $extra */
    public static function error(string $code, string $message, int $status, array $extra = []): self
    {
        return new self($status, ['error' => $code, 'message' => $message, ...$extra]);
    }
}
