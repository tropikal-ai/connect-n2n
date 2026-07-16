<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

/** Plain-ORM-style entity mirroring the canary's blog\bo\Article. */
final class SampleArticle
{
    private ?int $id = null;

    private string $title = '';

    private string $lead = '';

    private bool $online = false;

    private ?SampleCategory $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getLead(): string
    {
        return $this->lead;
    }

    public function setLead(string $lead): void
    {
        $this->lead = $lead;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    public function getCategory(): ?SampleCategory
    {
        return $this->category;
    }

    public function setCategory(?SampleCategory $category): void
    {
        $this->category = $category;
    }
}
