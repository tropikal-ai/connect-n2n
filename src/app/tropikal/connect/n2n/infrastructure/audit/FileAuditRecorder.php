<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\audit;

use tropikal\connect\n2n\domain\port\AuditRecorder;

/**
 * Appends one JSON line per mutating operation. The application layer passes
 * only field keys (never values) in the metadata, so the trail records what
 * changed without logging record content.
 */
final readonly class FileAuditRecorder implements AuditRecorder
{
    public function __construct(private string $path) {}

    public function record(?string $installationId, string $resourceSlug, int|string|null $recordId, string $operation, array $metadata = []): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return;
        }

        $line = json_encode([
            'installation_id' => $installationId,
            'resource' => $resourceSlug,
            'record_id' => $recordId,
            'operation' => $operation,
            'metadata' => $metadata,
        ], JSON_UNESCAPED_SLASHES);

        if ($line !== false) {
            @file_put_contents($this->path, $line."\n", FILE_APPEND | LOCK_EX);
        }
    }
}
