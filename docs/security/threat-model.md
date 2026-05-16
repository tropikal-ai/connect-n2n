# Threat Model

## Assets

- Local connection identifiers.
- Server signing secret.
- Rocket entity data.
- Rocket admin identity.
- Entity grants.

## Boundaries

- Rocket admin setup is browser-facing and requires Rocket admin or superadmin access.
- Bridge execution is server-to-server and must be signed.
- Tropikal backend turns granted entities into chat/workflow functions. That layer is outside this package.

## Controls

- OAuth/registration payloads contain safe public metadata only.
- Signed requests include method, path, normalized query, timestamp, nonce, body hash, and installation ID.
- Nonce claims are atomic.
- Secrets are encrypted at rest in the local state store.
- Secret-shaped keys are rejected in public payloads.
- Reads project declared fields only.
- Writes accept declared writable fields only.
- Delete requires an explicit Delete grant.
- Mutations are audit logged with redacted payloads.
