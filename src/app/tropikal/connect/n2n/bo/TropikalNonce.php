<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\bo;

final readonly class TropikalNonce
{
    public function __construct(
        public string $installationId,
        public string $nonceHash,
        public string $claimedAt,
    ) {}
}
