# Threat Model

## Assets

- The server signing secret issued by the control plane.
- The OAuth pending authorization (state hash, PKCE verifier).
- Site entity data exposed as resources.
- Per-resource grants.
- Admin access to the connect surface.

## Boundaries

- `/connect/admin` is browser-facing and guarded by the host's `AdminGate`; the
  OAuth callback is protected by the hashed single-use state + exact redirect
  URI match instead.
- The resource API and schema are server-to-server and must be signed.
- The TROPIKAL backend turns granted resources into chat/workflow functions;
  that layer is outside this package.

## Controls

- OAuth 2.1 + PKCE (S256) with dynamic client registration; no hand-entered or
  copied secrets anywhere in the flow.
- The entire connect state (secret included) is one AES-256-GCM document with an
  HKDF-SHA256-derived key and per-write random salt/IV; tampering or a wrong key
  fails closed to disconnected. Registration fails closed: no partial connected
  state is persisted.
- Signed requests cover method, path, normalized query, timestamp, nonce, body
  hash, and installation id; verification is constant-time via the shared core
  lib and is asserted byte-for-byte against the cross-language contract vector.
- Nonce claims are atomic; the PDO store extends single-use enforcement across
  all nodes sharing a database.
- Reads project only declared readable fields; writes accept only declared
  writable fields; unknown fields are rejected with 422; delete requires an
  explicit grant.
- Mutations are audit logged by field key — record content never enters the log.
- Error responses never carry internal exception details; the public health
  endpoint discloses liveness only.
