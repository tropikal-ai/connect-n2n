<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use ReflectionClass;
use Stringable;

final readonly class FakeRocket
{
    public function __construct(private FakeSpec $spec) {}

    public function getSpec(): FakeSpec
    {
        return $this->spec;
    }
}

final readonly class FakeSpec
{
    /** @param array<int, FakeEiType> $types */
    public function __construct(private array $types) {}

    public function getEiTypes(): array
    {
        return $this->types;
    }
}

final readonly class FakeEiType
{
    public function __construct(
        private string $id,
        private string $className,
        private FakeMask $mask,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getClass(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    public function getEiMask(): FakeMask
    {
        return $this->mask;
    }
}

final readonly class FakeMask
{
    public function __construct(
        private string $label,
        private FakeCollection $props,
        private FakeCollection $commands,
    ) {}

    public function getPluralLabelLstr(): FakeLabel
    {
        return new FakeLabel($this->label);
    }

    public function getEiPropCollection(): FakeCollection
    {
        return $this->props;
    }

    public function getEiCmdCollection(): FakeCollection
    {
        return $this->commands;
    }
}

final readonly class FakeCollection
{
    public function __construct(private array $items) {}

    public function toArray(): array
    {
        return $this->items;
    }
}

final readonly class FakeProp
{
    public function __construct(
        private string $path,
        private object $nature,
    ) {}

    public function getEiPropPath(): string
    {
        return $this->path;
    }

    public function getNature(): object
    {
        return $this->nature;
    }
}

class FakeStringNature
{
    public function __construct(private string $label = 'Title') {}

    public function getLabelLstr(): FakeLabel
    {
        return new FakeLabel($this->label);
    }

    public function isPrivileged(): bool
    {
        return false;
    }

    public function isPropFork(): bool
    {
        return false;
    }
}

final class FakeBoolNature extends FakeStringNature {}

final class FakeOnlineStatusNature extends FakeStringNature {}

final class FakePrivilegedNature extends FakeStringNature
{
    public function isPrivileged(): bool
    {
        return true;
    }
}

final readonly class FakeCommand
{
    public function __construct(private object $nature, private string $path = '') {}

    public function getNature(): object
    {
        return $this->nature;
    }

    public function getEiCmdPath(): string
    {
        return $this->path;
    }
}

final class FakeOverviewCommand {}

final class FakeAddCommand {}

final class FakeEditCommand {}

final class FakeDeleteCommand {}

final readonly class FakeLabel implements Stringable
{
    public function __construct(private string $label) {}

    public function __toString(): string
    {
        return $this->label;
    }
}

final class PageEntity {}

final class RocketUserEntity {}
