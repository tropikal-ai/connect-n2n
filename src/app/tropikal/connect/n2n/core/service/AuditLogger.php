<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

final readonly class AuditLogger
{
    public function __construct(private string $path) {}

    public function record(string $installationId, string $entityKey, string $operation, string $status, array $context = []): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Unable to create audit directory: {$dir}");
        }

        $entry = [
            'timestamp' => gmdate(DATE_ATOM),
            'installation_id' => $installationId,
            'entity_key' => $entityKey,
            'operation' => $operation,
            'status' => $status,
            'context' => PublicPayloadGuard::redact($context),
        ];

        file_put_contents($this->path, json_encode($entry, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        @chmod($this->path, 0600);
    }
}
