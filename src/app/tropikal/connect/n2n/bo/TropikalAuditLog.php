<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\bo;

final readonly class TropikalAuditLog
{
    public function __construct(
        public string $installationId,
        public string $entityKey,
        public string $operation,
        public string $status,
        public string $createdAt,
    ) {}
}
