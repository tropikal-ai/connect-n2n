<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\orm;

/**
 * Binds a resource slug to a concrete n2n ORM entity class and declares which
 * fields are to-one relations. A relation field key like 'categoryId' is read
 * via getCategory()->getId() and written by resolving the related record and
 * calling setCategory().
 */
final readonly class OrmResourceBinding
{
    /**
     * @param  class-string  $className
     * @param  array<string, class-string>  $relations  relation field key => related entity class
     */
    public function __construct(
        public string $className,
        public array $relations = [],
    ) {}

    public function isRelation(string $fieldKey): bool
    {
        return isset($this->relations[$fieldKey]);
    }

    /** 'categoryId' => 'Category' */
    public function relationAccessorBase(string $fieldKey): string
    {
        $base = str_ends_with($fieldKey, 'Id') ? substr($fieldKey, 0, -2) : $fieldKey;

        return ucfirst($base);
    }
}
