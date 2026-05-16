<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\model;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class ConnectN2nState
{
    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    /**
     * @param  array<string, array<int, string>>  $entityGrants
     */
    public function __construct(
        public ?string $installationId = null,
        public ?string $publicId = null,
        public ?string $serverSigningSecret = null,
        public ?string $keyId = null,
        public array $entityGrants = [],
        public bool $revoked = false,
        public ?string $createdByUserId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $lastSuccessfulSyncAt = null,
        public ?string $accountLabel = null,
    ) {}

    public static function disconnected(): self
    {
        return new self;
    }

    public function isConnected(): bool
    {
        return ! $this->revoked
            && $this->installationId !== null
            && $this->installationId !== ''
            && $this->serverSigningSecret !== null
            && $this->serverSigningSecret !== '';
    }

    public function grantsFor(string $entityKey): array
    {
        $grants = $this->entityGrants[$entityKey] ?? [];

        return array_values(array_intersect($grants, ['read', 'write', 'delete']));
    }

    public function allows(string $entityKey, string $grant): bool
    {
        return in_array($grant, $this->grantsFor($entityKey), true);
    }

    public function withGrant(string $entityKey, string $grant, bool $enabled): self
    {
        if (! in_array($grant, ['read', 'write', 'delete'], true)) {
            throw new \InvalidArgumentException('Entity grant must be read, write, or delete.');
        }

        $grants = $this->entityGrants;
        $entityGrants = $this->grantsFor($entityKey);
        if ($enabled) {
            $entityGrants[] = $grant;
        } else {
            $entityGrants = array_values(array_diff($entityGrants, [$grant]));
        }

        if ($entityGrants === []) {
            unset($grants[$entityKey]);
        } else {
            $grants[$entityKey] = array_values(array_unique($entityGrants));
        }

        return new self(
            $this->installationId,
            $this->publicId,
            $this->serverSigningSecret,
            $this->keyId,
            $grants,
            $this->revoked,
            $this->createdByUserId,
            $this->createdAt,
            gmdate(DATE_ATOM),
            $this->lastSuccessfulSyncAt,
            $this->accountLabel,
        );
    }

    public function revoked(): self
    {
        return new self(
            $this->installationId,
            $this->publicId,
            $this->serverSigningSecret,
            $this->keyId,
            [],
            true,
            $this->createdByUserId,
            $this->createdAt,
            gmdate(DATE_ATOM),
            $this->lastSuccessfulSyncAt,
            $this->accountLabel,
        );
    }

    public function safeStatus(): array
    {
        $payload = [
            'status' => $this->revoked ? self::STATUS_REVOKED : ($this->isConnected() ? self::STATUS_CONNECTED : self::STATUS_NOT_CONNECTED),
            'public_id' => $this->publicId,
            'account_label' => $this->accountLabel,
            'granted_entity_count' => count($this->entityGrants),
            'last_successful_sync_at' => $this->lastSuccessfulSyncAt,
            'updated_at' => $this->updatedAt,
        ];
        PublicPayloadGuard::assertPublicPayload($payload);

        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }
}
