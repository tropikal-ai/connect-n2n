<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class EntityDescriptor
{
    /**
     * @param  array<string, FieldDescriptor>  $fields
     * @param  array<int, string>  $operations
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $readable,
        public bool $writable,
        public bool $deletable,
        public array $fields,
        public array $operations,
        public string $sourceKind = 'n2n_rocket',
    ) {
        PublicPayloadGuard::assertPublicKey($this->key);
        foreach ($this->fields as $fieldKey => $field) {
            PublicPayloadGuard::assertPublicKey((string) $fieldKey);
        }
        PublicPayloadGuard::assertPublicPayload($this->toArray());
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    public function field(string $key): ?FieldDescriptor
    {
        return $this->fields[$key] ?? null;
    }

    public function toArray(): array
    {
        return [
            'source_kind' => $this->sourceKind,
            'entity_key' => $this->key,
            'entity_label' => $this->label,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'deletable' => $this->deletable,
            'fields' => array_map(
                static fn (FieldDescriptor $field): array => $field->toArray(),
                array_values($this->fields),
            ),
            'operations' => array_values($this->operations),
        ];
    }
}
