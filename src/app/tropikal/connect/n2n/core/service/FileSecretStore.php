<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\core\model\ConnectN2nState;

final readonly class FileSecretStore implements SecretStore
{
    public function __construct(
        private string $path,
        private string $encryptionKey,
    ) {
        if (strlen($this->encryptionKey) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters.');
        }
    }

    public function load(): ConnectN2nState
    {
        if (! is_file($this->path)) {
            return ConnectN2nState::disconnected();
        }

        $json = file_get_contents($this->path);
        $data = json_decode(is_string($json) ? $json : '', true);
        if (! is_array($data)) {
            return ConnectN2nState::disconnected();
        }

        $secret = null;
        if (is_string($data['server_signing_secret_encrypted'] ?? null)) {
            $secret = $this->decrypt($data['server_signing_secret_encrypted']);
        }

        return new ConnectN2nState(
            $this->stringOrNull($data['installation_id'] ?? null),
            $this->stringOrNull($data['public_id'] ?? null),
            $secret,
            $this->stringOrNull($data['key_id'] ?? null),
            is_array($data['entity_grants'] ?? null) ? $this->sanitizeGrants($data['entity_grants']) : [],
            (bool) ($data['revoked'] ?? false),
            $this->stringOrNull($data['created_by_user_id'] ?? null),
            $this->stringOrNull($data['created_at'] ?? null),
            $this->stringOrNull($data['updated_at'] ?? null),
            $this->stringOrNull($data['last_successful_sync_at'] ?? null),
            $this->stringOrNull($data['account_label'] ?? null),
        );
    }

    public function save(ConnectN2nState $state): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Unable to create connect-n2n state directory: {$dir}");
        }

        $data = array_filter([
            'installation_id' => $state->installationId,
            'public_id' => $state->publicId,
            'server_signing_secret_encrypted' => $state->serverSigningSecret !== null ? $this->encrypt($state->serverSigningSecret) : null,
            'key_id' => $state->keyId,
            'entity_grants' => $state->entityGrants,
            'revoked' => $state->revoked,
            'created_by_user_id' => $state->createdByUserId,
            'created_at' => $state->createdAt,
            'updated_at' => $state->updatedAt ?? gmdate(DATE_ATOM),
            'last_successful_sync_at' => $state->lastSuccessfulSyncAt,
            'account_label' => $state->accountLabel,
        ], static fn (mixed $value): bool => $value !== null);

        PublicPayloadGuard::assertPublicPayload([
            'installation_id' => $data['installation_id'] ?? null,
            'public_id' => $data['public_id'] ?? null,
            'key_id' => $data['key_id'] ?? null,
            'entity_grants' => $data['entity_grants'],
        ]);

        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($this->path, 0600);
    }

    public function delete(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    private function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (! is_string($cipher)) {
            throw new \RuntimeException('Unable to encrypt connect-n2n secret.');
        }

        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'cipher' => base64_encode($cipher),
        ], JSON_THROW_ON_ERROR));
    }

    private function decrypt(string $encoded): string
    {
        $payload = json_decode(base64_decode($encoded, true) ?: '', true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Unable to decrypt connect-n2n secret.');
        }

        $plain = openssl_decrypt(
            base64_decode((string) ($payload['cipher'] ?? ''), true) ?: '',
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            base64_decode((string) ($payload['iv'] ?? ''), true) ?: '',
            base64_decode((string) ($payload['tag'] ?? ''), true) ?: '',
        );
        if (! is_string($plain)) {
            throw new \RuntimeException('Unable to decrypt connect-n2n secret.');
        }

        return $plain;
    }

    private function key(): string
    {
        return hash('sha256', $this->encryptionKey, true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function sanitizeGrants(array $grants): array
    {
        $safe = [];
        foreach ($grants as $entityKey => $entityGrants) {
            if (! is_string($entityKey) || ! is_array($entityGrants)) {
                continue;
            }
            PublicPayloadGuard::assertPublicKey($entityKey);
            $safe[$entityKey] = array_values(array_intersect($entityGrants, ['read', 'write', 'delete']));
        }

        return array_filter($safe);
    }
}
