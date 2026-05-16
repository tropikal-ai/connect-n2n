<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\exception\UnauthorizedConnectException;

final class AdminAuthorizer
{
    public function requireAdmin(object $loginContext): array
    {
        if (! method_exists($loginContext, 'hasCurrentUser') || ! $loginContext->hasCurrentUser()) {
            throw new UnauthorizedConnectException('Rocket admin login is required.');
        }
        if (! method_exists($loginContext, 'getCurrentUser')) {
            throw new UnauthorizedConnectException('Rocket admin login is required.');
        }

        $user = $loginContext->getCurrentUser();
        if (! is_object($user) || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            throw new UnauthorizedConnectException('Rocket admin privileges are required.');
        }

        return array_filter([
            'id' => $this->callString($user, 'getId'),
            'nick' => $this->callString($user, 'getNick'),
            'email' => $this->callString($user, 'getEmail'),
            'is_super_admin' => method_exists($user, 'isSuperAdmin') ? (bool) $user->isSuperAdmin() : false,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function callString(object $object, string $method): ?string
    {
        if (! method_exists($object, $method)) {
            return null;
        }

        $value = $object->{$method}();

        return is_scalar($value) ? (string) $value : null;
    }
}
