<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use ReflectionClass;
use Throwable;
use tropikal\connect\n2n\dto\EntityDescriptor;
use tropikal\connect\n2n\dto\FieldDescriptor;

final readonly class RocketSchemaMapper
{
    /**
     * @param  array<int, string>  $excludedEntityPatterns
     * @param  array<int, string>  $excludedFieldPatterns
     */
    public function __construct(
        private array $excludedEntityPatterns = [],
        private array $excludedFieldPatterns = [],
    ) {}

    /** @return array<string, EntityDescriptor> */
    public function mapSpec(object $spec): array
    {
        if (! method_exists($spec, 'getEiTypes')) {
            return [];
        }

        $entities = [];
        foreach ((array) $spec->getEiTypes() as $eiType) {
            if (! is_object($eiType)) {
                continue;
            }
            $entity = $this->mapEiType($eiType);
            if ($entity !== null) {
                $entities[$entity->key] = $entity;
            }
        }
        ksort($entities);

        return $entities;
    }

    public function mapEiType(object $eiType): ?EntityDescriptor
    {
        $id = $this->safeKey($this->stringFromMethod($eiType, 'getId'));
        if ($id === null) {
            return null;
        }

        $className = $this->className($eiType);
        if ($this->isExcludedEntity($id, $className)) {
            return null;
        }

        $mask = $this->objectFromMethod($eiType, 'getEiMask');
        if (! $mask) {
            return null;
        }

        $fields = $this->fields($mask);
        $operations = $this->operations($mask);
        if ($fields === [] || $operations === []) {
            return null;
        }

        return new EntityDescriptor(
            $id,
            $this->label($mask, $id),
            in_array('list', $operations, true) || in_array('get', $operations, true),
            in_array('create', $operations, true) || in_array('update', $operations, true),
            in_array('delete', $operations, true),
            $fields,
            $operations,
        );
    }

    /** @return array<string, FieldDescriptor> */
    private function fields(object $mask): array
    {
        $collection = $this->objectFromMethod($mask, 'getEiPropCollection');
        if (! $collection || ! method_exists($collection, 'toArray')) {
            return [];
        }

        $fields = [];
        foreach ((array) $collection->toArray() as $prop) {
            if (! is_object($prop)) {
                continue;
            }
            $field = $this->field($prop);
            if ($field !== null) {
                $fields[$field->key] = $field;
            }
        }
        ksort($fields);

        return $fields;
    }

    private function field(object $prop): ?FieldDescriptor
    {
        $path = $this->stringFromMethod($prop, 'getEiPropPath');
        $key = $this->safeKey($path);
        if ($key === null || $this->isExcludedField($key)) {
            return null;
        }

        $nature = $this->objectFromMethod($prop, 'getNature');
        if (! $nature || $this->isPrivileged($nature) || $this->isFork($nature)) {
            return null;
        }

        $type = $this->fieldType($nature);
        $writable = ! in_array($type, ['relation', 'file', 'image', 'online_status', 'order'], true);

        return new FieldDescriptor(
            $key,
            $this->fieldLabel($nature, $key),
            $type,
            true,
            $writable,
        );
    }

    /** @return array<int, string> */
    private function operations(object $mask): array
    {
        $collection = $this->objectFromMethod($mask, 'getEiCmdCollection');
        if (! $collection || ! method_exists($collection, 'toArray')) {
            return ['list', 'get'];
        }

        $operations = [];
        foreach ((array) $collection->toArray() as $cmd) {
            if (! is_object($cmd)) {
                continue;
            }
            $nature = $this->objectFromMethod($cmd, 'getNature');
            $name = $nature ? $this->shortName($nature) : $this->shortName($cmd);
            $path = strtolower($this->stringFromMethod($cmd, 'getEiCmdPath') ?? '');

            if (str_contains($name, 'Overview') || str_contains($path, 'overview')) {
                array_push($operations, 'list', 'get');
            }
            if (str_contains($name, 'Add') || str_contains($path, 'add')) {
                $operations[] = 'create';
            }
            if (str_contains($name, 'Edit') || str_contains($path, 'edit')) {
                $operations[] = 'update';
            }
            if (str_contains($name, 'Delete') || str_contains($path, 'delete')) {
                $operations[] = 'delete';
            }
        }

        return array_values(array_unique($operations ?: ['list', 'get']));
    }

    private function isExcludedEntity(string $id, ?string $className): bool
    {
        $candidate = strtolower($id.' '.$className);
        foreach (['rocketuser', 'rocket_user', 'user', 'auth', 'security', 'session', 'password', 'token'] as $marker) {
            if (str_contains($candidate, $marker)) {
                return true;
            }
        }

        foreach ($this->excludedEntityPatterns as $pattern) {
            if (@preg_match($pattern, $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedField(string $key): bool
    {
        if (PublicPayloadGuard::isSensitiveKey($key)) {
            return true;
        }

        foreach ($this->excludedFieldPatterns as $pattern) {
            if (@preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isPrivileged(object $nature): bool
    {
        return method_exists($nature, 'isPrivileged') && (bool) $nature->isPrivileged();
    }

    private function isFork(object $nature): bool
    {
        return method_exists($nature, 'isPropFork') && (bool) $nature->isPropFork();
    }

    private function fieldType(object $nature): string
    {
        $name = $this->shortName($nature);

        return match (true) {
            str_contains($name, 'Bool') => 'boolean',
            str_contains($name, 'Decimal') => 'decimal',
            str_contains($name, 'Enum') => 'string',
            str_contains($name, 'Cke') => 'text',
            str_contains($name, 'ImageFile') => 'image',
            str_contains($name, 'File') => 'file',
            str_contains($name, 'OnlineStatus') => 'online_status',
            str_contains($name, 'Order') => 'order',
            str_contains($name, 'Embedded'), str_contains($name, 'Integrated') => 'relation',
            default => 'string',
        };
    }

    private function label(object $mask, string $fallback): string
    {
        foreach (['getPluralLabelLstr', 'getLabelLstr'] as $method) {
            $label = $this->labelFromMethod($mask, $method);
            if ($label !== null) {
                return $label;
            }
        }

        return $this->humanize($fallback);
    }

    private function fieldLabel(object $nature, string $fallback): string
    {
        return $this->labelFromMethod($nature, 'getLabelLstr') ?? $this->humanize($fallback);
    }

    private function labelFromMethod(object $object, string $method): ?string
    {
        $value = $this->valueFromMethod($object, $method);
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function className(object $eiType): ?string
    {
        $class = $this->valueFromMethod($eiType, 'getClass');
        if ($class instanceof ReflectionClass) {
            return $class->getName();
        }

        return null;
    }

    private function objectFromMethod(object $object, string $method): ?object
    {
        $value = $this->valueFromMethod($object, $method);

        return is_object($value) ? $value : null;
    }

    private function stringFromMethod(object $object, string $method): ?string
    {
        $value = $this->valueFromMethod($object, $method);

        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : null;
    }

    private function valueFromMethod(object $object, string $method): mixed
    {
        if (! method_exists($object, $method)) {
            return null;
        }

        try {
            return $object->{$method}();
        } catch (Throwable) {
            return null;
        }
    }

    private function safeKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }
        $key = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $key) ?? '';
        $key = trim($key, '._-');
        if ($key === '') {
            return null;
        }
        if (PublicPayloadGuard::isSensitiveKey($key)) {
            return null;
        }

        return $key;
    }

    private function shortName(object $object): string
    {
        return (new ReflectionClass($object))->getShortName();
    }

    private function humanize(string $value): string
    {
        $value = preg_replace('/[_\-.]+/', ' ', $value) ?? $value;
        $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value) ?? $value;

        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }
}
