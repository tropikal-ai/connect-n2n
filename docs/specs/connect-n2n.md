# TROPIKAL Connect n2n Spec

`tropikal-ai/connect-n2n` connects a plain-ORM n2n site to TROPIKAL Connect.

## Model

The site declares its connectable **resources** in code (`ResourceSpec` +
`FieldSpec`), binds them to n2n ORM entities (`OrmResourceBinding`), and grants
per-resource permissions: `read`, `create`, `update`, `delete`. Empty grants
expose nothing. Only declared fields are readable or writable.

## Connect lifecycle

1. An admin opens `/connect/admin` (behind the host's `AdminGate`) and clicks
   **Connect**.
2. `ConnectFlow::begin()` performs OAuth 2.1 dynamic client registration if no
   client is configured, generates a PKCE pair and a hashed single-use state,
   persists the pending authorization, and redirects to the authorization
   server.
3. The callback validates the state (hash + expiry) and the redirect URI, then
   exchanges the code + verifier for tokens.
4. `ConnectFlow::complete()` registers the installation on the control plane
   (Bearer access token; payload carries the site URL, API base URL, and the
   capability manifest) and stores the returned `server_signing_key` — the
   whole connect state is a single AES-256-GCM-encrypted document
   (HKDF-SHA256-derived key, per-write random salt/IV).

No secret is ever typed, copied, or stored in plaintext.

## Resource API (what a TROPIKAL job calls)

All requests carry the five `X-Tropikal-Connect-*` headers and are verified
with `tropikal-ai/connect` (HMAC-SHA256 canonical, 300 s timestamp tolerance,
single-use nonce):

| Route | Operation | Grant |
|---|---|---|
| `GET /resources/{slug}` | list (page, per_page, search) | read |
| `GET /resources/{slug}/{id}` | get | read |
| `POST /resources/{slug}` | create | create |
| `PUT/PATCH /resources/{slug}/{id}` | update | update |
| `DELETE /resources/{slug}/{id}` | delete | delete |
| `GET /schema` | capability manifest | — (signed) |
| `GET /health` | liveness only | — (public) |

Responses: list returns `{data, meta{current_page, per_page, total, last_page}}`;
mutations return the projected record; errors return `{error, message, ...}`
with precise status codes (401 signature, 403 grant, 404 unknown, 422 fields).

The capability manifest is emitted through the core lib
(`CapabilityDescriptor`/`OperationDescriptor`): operations `{slug}.list/get/
create/update/delete` with `risk_level` (read/write/destructive),
`requires_confirmation`, and JSON input/output schemas — the same shape the
Filament adapter produces.

## Replay protection

`PdoNonceStore` claims each nonce with a single INSERT guarded by a database
unique constraint — atomic across all nodes sharing the database. The
file-based `FileNonceStore` remains available for single-host setups.

## Interop guarantee

`tests/ContractSigningVectorTest` asserts the signing canonical and HMAC
byte-for-byte against the shared cross-language contract fixture
(`signed-request.vector.json`). Canonicalization drift fails CI.
