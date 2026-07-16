<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

/** Plain-ORM-style entity mirroring the canary's blog\bo\Category. */
final class SampleCategory
{
    private ?int $id = null;

    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
