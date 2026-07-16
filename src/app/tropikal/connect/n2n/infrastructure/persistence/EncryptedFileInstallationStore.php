<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\persistence;

use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\oauth\PendingAuthorization;
use tropikal\connect\n2n\domain\port\InstallationStore;

/**
 * Persists the whole connect state (installation + pending authorization) as a
 * single AES-256-GCM-encrypted JSON document. The entire document is
 * ciphertext — no field, key name, or grant ever touches disk in plaintext —
 * and GCM authenticates it, so tampering or a wrong key fails closed to
 * disconnected. Key derivation: HKDF-SHA256 with a per-file random salt.
 */
final readonly class EncryptedFileInstallationStore implements InstallationStore
{
    public function __construct(
        private string $path,
        private string $encryptionKey,
    ) {
        if (strlen($this->encryptionKey) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters.');
        }
    }

    public function current(): Installation
    {
        return $this->loadInstallation();
    }

    public function loadInstallation(): Installation
    {
        $doc = $this->read();
        $data = is_array($doc['installation'] ?? null) ? $doc['installation'] : null;
        if ($data === null) {
            return Installation::disconnected();
        }

        return new Installation(
            $this->stringOrNull($data['installation_id'] ?? null),
            $this->stringOrNull($data['public_id'] ?? null),
            $this->stringOrNull($data['signing_secret'] ?? null),
            is_array($data['allowed_resources'] ?? null) ? array_values($data['allowed_resources']) : [],
            is_array($data['resource_permissions'] ?? null) ? $data['resource_permissions'] : [],
            (bool) ($data['revoked'] ?? false),
        );
    }

    public function saveInstallation(Installation $installation): void
    {
        $doc = $this->read();
        $doc['installation'] = [
            'installation_id' => $installation->installationId,
            'public_id' => $installation->publicId,
            'signing_secret' => $installation->signingSecret,
            'allowed_resources' => $installation->allowedResources,
            'resource_permissions' => $installation->resourcePermissions,
            'revoked' => $installation->revoked,
        ];
        $this->write($doc);
    }

    public function loadPending(): ?PendingAuthorization
    {
        $doc = $this->read();
        $data = is_array($doc['pending'] ?? null) ? $doc['pending'] : null;
        if ($data === null) {
            return null;
        }

        try {
            return new PendingAuthorization(
                (string) $data['client_id'],
                (string) $data['state_hash'],
                (string) $data['code_verifier'],
                new \DateTimeImmutable((string) $data['expires_at']),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function savePending(?PendingAuthorization $pending): void
    {
        $doc = $this->read();
        $doc['pending'] = $pending === null ? null : [
            'client_id' => $pending->clientId,
            'state_hash' => $pending->stateHash,
            'code_verifier' => $pending->codeVerifier,
            'expires_at' => $pending->expiresAt->format(DATE_ATOM),
        ];
        $this->write($doc);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $envelope = json_decode((string) file_get_contents($this->path), true);
        if (! is_array($envelope)) {
            return [];
        }

        try {
            $salt = (string) base64_decode((string) ($envelope['salt'] ?? ''), true);
            $iv = (string) base64_decode((string) ($envelope['iv'] ?? ''), true);
            $tag = (string) base64_decode((string) ($envelope['tag'] ?? ''), true);
            $cipher = (string) base64_decode((string) ($envelope['data'] ?? ''), true);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key($salt), OPENSSL_RAW_DATA, $iv, $tag);
            $doc = is_string($plain) ? json_decode($plain, true) : null;

            return is_array($doc) ? $doc : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $doc */
    private function write(array $doc): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Unable to create connect state directory: '.$dir);
        }

        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt((string) json_encode($doc, JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key($salt), OPENSSL_RAW_DATA, $iv, $tag);
        if (! is_string($cipher)) {
            throw new \RuntimeException('Unable to encrypt connect state.');
        }

        $envelope = json_encode([
            'v' => 1,
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_THROW_ON_ERROR);

        file_put_contents($this->path, $envelope, LOCK_EX);
        @chmod($this->path, 0600);
    }

    private function key(string $salt): string
    {
        return hash_hkdf('sha256', $this->encryptionKey, 32, 'tropikal-connect-n2n-state', $salt);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
