<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\http;

use tropikal\connect\n2n\application\ConnectConfig;
use tropikal\connect\n2n\domain\exception\OAuthException;
use tropikal\connect\n2n\domain\port\AuthorizationServerGateway;
use TropikalAI\Connect\Domain\OAuth\ClientRegistrationRequest;
use TropikalAI\Connect\Domain\OAuth\TokenRequest;
use TropikalAI\Connect\Domain\OAuth\TokenSet;

/** Talks OAuth to the TROPIKAL authorization server over HTTPS. */
final readonly class HttpAuthorizationServerGateway implements AuthorizationServerGateway
{
    public function __construct(
        private ConnectConfig $config,
        private HttpJson $http,
    ) {}

    public function registerClient(ClientRegistrationRequest $request): string
    {
        $body = $this->http->postJson($this->url($this->config->registerClientPath), $request->toArray());
        $clientId = trim((string) ($body['client_id'] ?? ''));
        if ($clientId === '') {
            throw new OAuthException('The authorization server returned an invalid client registration response.');
        }

        return $clientId;
    }

    public function exchangeCode(string $clientId, string $redirectUri, string $code, string $verifier, string $resource): TokenSet
    {
        $body = $this->http->postForm(
            $this->url($this->config->tokenPath),
            TokenRequest::authorizationCode($clientId, $redirectUri, $code, $verifier, $resource),
        );

        try {
            return TokenSet::fromArray($body);
        } catch (\InvalidArgumentException $e) {
            throw new OAuthException($e->getMessage(), previous: $e);
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->config->authorizationServerUrl, '/').$path;
    }
}
