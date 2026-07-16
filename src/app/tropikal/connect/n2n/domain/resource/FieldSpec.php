<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\resource;

/** Describes one field of a connectable resource. */
final readonly class FieldSpec
{
    public function __construct(
        public string $name,
        public string $type = 'string',
        public bool $readable = true,
        public bool $writable = false,
        public bool $required = false,
    ) {}
}
