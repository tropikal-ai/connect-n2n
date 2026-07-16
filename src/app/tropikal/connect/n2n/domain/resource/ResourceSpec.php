<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\resource;

/**
 * The public contract of one connectable resource (e.g. article, category):
 * its slug, label, identifier column, and field specs. Pure domain — it knows
 * nothing about persistence or HTTP.
 */
final readonly class ResourceSpec
{
    /** @var array<string, FieldSpec> */
    public array $fields;

    /**
     * @param  array<int, FieldSpec>  $fields
     * @param  array<int, string>  $searchable  field names searchable in list()
     */
    public function __construct(
        public string $slug,
        public string $label,
        array $fields,
        public string $identifier = 'id',
        public array $searchable = [],
        public string $sortColumn = 'id',
    ) {
        $keyed = [];
        foreach ($fields as $field) {
            $keyed[$field->name] = $field;
        }
        $this->fields = $keyed;
    }

    public function field(string $name): ?FieldSpec
    {
        return $this->fields[$name] ?? null;
    }

    /** @return array<int, string> */
    public function readableFieldNames(): array
    {
        return array_values(array_map(
            static fn (FieldSpec $f): string => $f->name,
            array_filter($this->fields, static fn (FieldSpec $f): bool => $f->readable),
        ));
    }

    /** @return array<int, string> */
    public function writableFieldNames(): array
    {
        return array_values(array_map(
            static fn (FieldSpec $f): string => $f->name,
            array_filter($this->fields, static fn (FieldSpec $f): bool => $f->writable),
        ));
    }

    /** @return array<int, string> */
    public function requiredFieldNames(): array
    {
        return array_values(array_map(
            static fn (FieldSpec $f): string => $f->name,
            array_filter($this->fields, static fn (FieldSpec $f): bool => $f->writable && $f->required),
        ));
    }
}
