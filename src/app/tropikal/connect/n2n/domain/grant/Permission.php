<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\grant;

/** A grantable permission on a resource. */
enum Permission: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
