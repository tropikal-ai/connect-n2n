<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\http;

use tropikal\connect\n2n\domain\exception\OAuthException;

/** Minimal curl helper shared by the outbound gateways. */
final readonly class HttpJson
{
    public function __construct(private int $timeoutSeconds = 10) {}

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $body, array $headers = []): array
    {
        return $this->post($url, (string) json_encode($body, JSON_THROW_ON_ERROR), ['Content-Type: application/json', ...$headers]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $form): array
    {
        return $this->post($url, http_build_query($form), ['Content-Type: application/x-www-form-urlencoded']);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function post(string $url, string $body, array $headers): array
    {
        $this->assertSecureEndpoint($url);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new OAuthException('Unable to initialise the HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Accept: application/json', ...$headers],
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($raw) || $error !== '') {
            throw new OAuthException('HTTP request failed: '.($error !== '' ? $error : 'no response'));
        }
        if ($status < 200 || $status >= 300) {
            throw new OAuthException("The server rejected the request with HTTP {$status}.");
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new OAuthException('The server returned an invalid JSON response.');
        }

        return $decoded;
    }

    /**
     * Refuse to send the authorization code, bearer token, or refresh token over
     * cleartext. https is required; http is tolerated only for loopback hosts so
     * local development against a mock server still works.
     */
    private function assertSecureEndpoint(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');

        if ($scheme !== 'https' && ! ($scheme === 'http' && $isLocal)) {
            throw new OAuthException('TROPIKAL endpoints must use https (http is allowed only for localhost).');
        }
    }
}
