<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\exception;

/**
 * A write payload violated the resource's field contract. Carries a stable
 * wire code (e.g. 'unknown_fields', 'missing_required') and the offending
 * field names for a precise, non-leaking error response.
 */
final class InvalidWriteException extends \RuntimeException
{
    /** @param array<int, string> $fields */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
