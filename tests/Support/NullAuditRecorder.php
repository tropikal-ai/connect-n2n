<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\domain\port\AuditRecorder;

final class NullAuditRecorder implements AuditRecorder
{
    public function record(?string $installationId, string $resourceSlug, int|string|null $recordId, string $operation, array $metadata = []): void
    {
        // intentionally no-op
    }
}
