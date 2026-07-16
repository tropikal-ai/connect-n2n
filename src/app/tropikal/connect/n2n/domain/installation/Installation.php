<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\installation;

use tropikal\connect\n2n\domain\grant\Permission;

/**
 * The connection between this site and TROPIKAL: its identifiers, signing secret,
 * and the per-resource grants an admin approved. Immutable aggregate — grant
 * changes return a new instance.
 */
final readonly class Installation
{
    /**
     * @param  array<int, string>  $allowedResources  resource slugs the admin exposed
     * @param  array<string, array<int, string>>  $resourcePermissions  slug => granted permission values
     */
    public function __construct(
        public ?string $installationId = null,
        public ?string $publicId = null,
        public ?string $signingSecret = null,
        public array $allowedResources = [],
        public array $resourcePermissions = [],
        public bool $revoked = false,
    ) {}

    public static function disconnected(): self
    {
        return new self;
    }

    public function isConnected(): bool
    {
        return ! $this->revoked
            && $this->installationId !== null
            && $this->signingSecret !== null;
    }

    public function allowsResource(string $slug): bool
    {
        return in_array($slug, $this->allowedResources, true);
    }

    public function allows(string $slug, Permission $permission): bool
    {
        return in_array($permission->value, $this->resourcePermissions[$slug] ?? [], true);
    }

    /** @return array<int, string> */
    public function permissionsFor(string $slug): array
    {
        return array_values($this->resourcePermissions[$slug] ?? []);
    }

    public function withGrant(string $slug, Permission $permission, bool $enabled): self
    {
        $permissions = $this->resourcePermissions;
        $current = $permissions[$slug] ?? [];
        $current = array_values(array_filter($current, static fn (string $p): bool => $p !== $permission->value));
        if ($enabled) {
            $current[] = $permission->value;
        }

        // exposure follows the grants symmetrically: a resource with no
        // permissions left is no longer exposed (and the empty entry dropped)
        if ($current === []) {
            unset($permissions[$slug]);
            $allowed = array_values(array_filter(
                $this->allowedResources,
                static fn (string $s): bool => $s !== $slug,
            ));
        } else {
            $permissions[$slug] = $current;
            $allowed = $this->allowedResources;
            if (! in_array($slug, $allowed, true)) {
                $allowed[] = $slug;
            }
        }

        return new self($this->installationId, $this->publicId, $this->signingSecret, $allowed, $permissions, $this->revoked);
    }
}
