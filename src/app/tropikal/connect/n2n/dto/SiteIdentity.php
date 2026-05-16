<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class SiteIdentity
{
    public function __construct(
        public string $name,
        public string $baseUrl,
        public string $packageVersion = '0.1.0',
        public ?string $rocketVersion = null,
        public string $integration = 'n2n-rocket',
    ) {}

    public function toArray(): array
    {
        $payload = array_filter([
            'name' => $this->name,
            'base_url' => rtrim($this->baseUrl, '/'),
            'integration' => $this->integration,
            'package_version' => $this->packageVersion,
            'rocket_version' => $this->rocketVersion,
        ], static fn (mixed $value): bool => $value !== null);

        PublicPayloadGuard::assertPublicPayload($payload);

        return $payload;
    }
}
