<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

use n2n\bind\build\impl\Bind;
use n2n\bind\mapper\impl\Mappers;
use n2n\bind\mapper\Mapper;
use n2n\util\type\TypeConstraints;
use n2n\validation\validator\impl\Validators;
use n2n\validation\validator\Validator;
use tropikal\connect\n2n\domain\exception\InvalidWriteException;
use tropikal\connect\n2n\domain\resource\FieldSpec;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * Binds an untrusted write payload to a resource's field contract using
 * n2n-bind, the framework's native binding layer: each declared field gets
 * type-appropriate mappers (clean strings, ranged ints, strict bools) and the
 * whole payload fails closed — unknown fields, missing required fields, and
 * type violations are rejected with stable wire codes before anything touches
 * the store.
 */
final readonly class ResourceWriteBinder
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the validated, mapper-cleaned payload
     *
     * @throws InvalidWriteException
     */
    public function bind(ResourceSpec $resource, array $data, bool $creating): array
    {
        $writable = $resource->writableFieldNames();

        $unknown = array_values(array_diff(array_keys($data), $writable));
        if ($unknown !== []) {
            throw new InvalidWriteException('unknown_fields', 'Unknown fields: '.implode(', ', $unknown), $unknown);
        }

        if ($creating) {
            $missing = array_values(array_diff($resource->requiredFieldNames(), array_keys($data)));
            if ($missing !== []) {
                throw new InvalidWriteException('missing_required', 'Missing required fields: '.implode(', ', $missing), $missing);
            }
        }

        $composer = Bind::attrs($data);
        foreach ($resource->fields as $field) {
            if (! $field->writable || ! array_key_exists($field->name, $data)) {
                continue;
            }
            $composer->prop($field->name, ...$this->mappersFor($field, $creating && $field->required));
        }

        $result = $composer->toArray()->exec();
        if (! $result->isValid()) {
            $fields = array_keys($result->getErrorMap()->getChildren());
            throw new InvalidWriteException('invalid_fields', 'Invalid fields: '.implode(', ', $fields), $fields);
        }

        /** @var array<string, mixed> $clean */
        $clean = $result->get();

        return array_intersect_key($clean, array_flip($writable));
    }

    /** @return array<int, Mapper|Validator> */
    private function mappersFor(FieldSpec $field, bool $mandatory): array
    {
        return match ($field->type) {
            'integer', 'int' => [Mappers::int($mandatory, min: null, max: null)],
            'boolean', 'bool' => $mandatory
                ? [Validators::mandatory(), Validators::type(TypeConstraints::bool())]
                : [Validators::type(TypeConstraints::bool(nullable: true))],
            'text' => [Mappers::cleanMultilineString($mandatory, minlength: null, maxlength: 65535)],
            default => [Mappers::cleanString($mandatory, minlength: null, maxlength: 65535)],
        };
    }
}
