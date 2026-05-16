<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\bo;

final class TropikalEntityGrant
{
    public function __construct(
        public string $entityKey,
        public bool $read = false,
        public bool $write = false,
        public bool $delete = false,
    ) {}

    public function grants(): array
    {
        return array_values(array_filter([
            $this->read ? 'read' : null,
            $this->write ? 'write' : null,
            $this->delete ? 'delete' : null,
        ]));
    }
}
