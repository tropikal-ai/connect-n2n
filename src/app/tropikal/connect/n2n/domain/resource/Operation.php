<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\resource;

use tropikal\connect\n2n\domain\grant\Permission;

/** A resource operation the connect job can invoke, with its grant and risk. */
enum Operation: string
{
    case List = 'list';
    case Get = 'get';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::List, self::Get => Permission::Read,
            self::Create => Permission::Create,
            self::Update => Permission::Update,
            self::Delete => Permission::Delete,
        };
    }

    public function riskLevel(): string
    {
        return match ($this) {
            self::List, self::Get => 'read',
            self::Create, self::Update => 'write',
            self::Delete => 'destructive',
        };
    }

    public function requiresConfirmation(): bool
    {
        return match ($this) {
            self::Create, self::Update, self::Delete => true,
            default => false,
        };
    }
}
