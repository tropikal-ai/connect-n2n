<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class FieldDescriptor
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public bool $readable = true,
        public bool $writable = false,
        public bool $required = false,
    ) {
        PublicPayloadGuard::assertPublicKey($this->key);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'required' => $this->required,
        ];
    }
}
