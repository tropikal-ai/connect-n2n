<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\bo;

final class TropikalConnection
{
    public function __construct(
        public ?string $installationId = null,
        public ?string $publicId = null,
        public ?string $keyId = null,
        public bool $revoked = false,
    ) {}
}
