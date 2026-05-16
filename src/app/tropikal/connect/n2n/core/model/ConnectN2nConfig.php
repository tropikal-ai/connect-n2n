<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\model;

final readonly class ConnectN2nConfig
{
    /**
     * @param  array<int, string>  $excludedEntityPatterns
     * @param  array<int, string>  $excludedFieldPatterns
     */
    public function __construct(
        public string $siteName,
        public string $baseUrl,
        public string $statePath,
        public string $encryptionKey,
        public int $signatureToleranceSeconds = 300,
        public int $maxListLimit = 100,
        public array $excludedEntityPatterns = [],
        public array $excludedFieldPatterns = [],
    ) {
        if (trim($this->siteName) === '') {
            throw new \InvalidArgumentException('Site name is required.');
        }
        if (filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Base URL must be absolute.');
        }
        if (trim($this->statePath) === '') {
            throw new \InvalidArgumentException('State path is required.');
        }
        if (strlen($this->encryptionKey) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters.');
        }
    }

    public static function fromArray(array $config): self
    {
        return new self(
            (string) ($config['site_name'] ?? 'Rocket Site'),
            (string) ($config['base_url'] ?? 'https://example.com'),
            (string) ($config['state_path'] ?? sys_get_temp_dir().'/tropikal-connect-n2n/state.json'),
            (string) ($config['encryption_key'] ?? ''),
            max(1, (int) ($config['signature_tolerance_seconds'] ?? 300)),
            max(1, (int) ($config['max_list_limit'] ?? 100)),
            array_values(array_filter((array) ($config['excluded_entity_patterns'] ?? []), 'is_string')),
            array_values(array_filter((array) ($config['excluded_field_patterns'] ?? []), 'is_string')),
        );
    }
}
