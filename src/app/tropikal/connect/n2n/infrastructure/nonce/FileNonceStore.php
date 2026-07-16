<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\nonce;

use TropikalAI\Connect\Application\Ports\NonceStore as ConnectNonceStore;

final readonly class FileNonceStore implements ConnectNonceStore
{
    public function __construct(private string $directory) {}

    public function claim(string $installationId, string $nonce, int $ttlSeconds): bool
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new \RuntimeException("Unable to create nonce directory: {$this->directory}");
        }

        $this->cleanup($ttlSeconds);
        $path = $this->directory.'/'.hash('sha256', $installationId.':'.$nonce).'.nonce';
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) time());
        fclose($handle);
        @chmod($path, 0600);

        return true;
    }

    private function cleanup(int $ttlSeconds): void
    {
        foreach (glob($this->directory.'/*.nonce') ?: [] as $path) {
            if (is_file($path) && filemtime($path) !== false && filemtime($path) < time() - $ttlSeconds) {
                @unlink($path);
            }
        }
    }
}
