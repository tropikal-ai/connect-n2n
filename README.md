# TROPIKAL Connect n2n

`tropikal-ai/connect-n2n` is the native n2n Rocket CMF integration for TROPIKAL Connect.

It connects a Rocket-powered site to TROPIKAL, discovers Rocket entities through Rocket's CMF spec, lets a Rocket admin grant Read/Write/Delete access per entity, and exposes signed server-to-server endpoints for safe execution.

This package does not implement MCP, owner chat, workflows, or an AI agent inside n2n. TROPIKAL backend imports granted entities and turns them into website chat and workflow functions.

## Requirements

- PHP 8.2+
- n2n 7.5
- Rocket 4.1 development branch until Rocket publishes a stable v4 tag
- `tropikal-ai/connect` 0.1+

## Install

```bash
composer require tropikal-ai/connect-n2n:^0.1
```

For local path development:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../connect-n2n"
    }
  ]
}
```

Then:

```bash
composer require tropikal-ai/connect-n2n:@dev
```

## Setup

Enable the module in the n2n application the same way as other Composer `n2n-module` packages. Open the Rocket admin route for TROPIKAL Connect, connect the site, then grant entity access.

The admin UI should show connected Rocket entities with separate controls:

- Read: list/search/get records.
- Write: create/update records.
- Delete: delete records.

Default access is none. Empty grants expose nothing.

## Rocket Entity Discovery

Discovery uses `rocket\core\model\Rocket::getSpec()` and maps Rocket EiTypes, EiMasks, EiProps, and command metadata into a safe manifest. Auth, admin, session, security, and secret-shaped fields are excluded by default.

## Security Model

- No hand-entered token setup.
- No copied secret setup.
- No browser-visible secrets.
- Setup requires Rocket admin or superadmin access.
- Bridge calls are signed with `tropikal-ai/connect`.
- Nonces are atomically claimed to prevent replay.
- Local signing secrets are encrypted at rest.
- Reads project declared fields only.
- Writes accept declared writable fields only.
- Delete requires an explicit Delete grant.
- Mutations are audit logged with redacted payloads.

## Bridge Operations

The signed bridge supports normalized operations when granted and supported by Rocket:

- `rocket.entity.list`
- `rocket.entity.get`
- `rocket.entity.create`
- `rocket.entity.update`
- `rocket.entity.delete`

Publishing is intentionally not enabled until a Rocket draft/publish flow is proven and approval metadata is enforced.

## Local Development

```bash
composer validate --strict
composer install --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/phpunit --colors=never
```

Use `https://example.com` style placeholder URLs in tests and docs.

See `docs/implementation-notes.md` for the current Rocket adapter status.
