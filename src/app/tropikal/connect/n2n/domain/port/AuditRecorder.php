<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

/** Records mutating operations for the audit trail. */
interface AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        ?string $installationId,
        string $resourceSlug,
        int|string|null $recordId,
        string $operation,
        array $metadata = [],
    ): void;
}
