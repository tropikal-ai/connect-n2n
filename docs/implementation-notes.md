# Implementation Notes

The package is a strict DDD build: `domain/` (pure model, rules, and ports —
zero framework imports), `application/` (use cases: `ResourceApi`,
`SignedRequestGuard`, `ConnectFlow`), `infrastructure/` (adapters), and `web/`
(reusable n2n `ControllerAdapter`s). The single seam that touches the n2n
`EntityManager` is `infrastructure/orm/N2nOrmSession`.

Implemented:

- One-click connect: OAuth 2.1 + PKCE, dynamic client registration, control
  plane installation registration, encrypted-at-rest state
  (`EncryptedFileInstallationStore`, AES-256-GCM + HKDF-SHA256).
- Signed RESTful resource API (list/get/create/update/delete + schema) with
  per-resource read/create/update/delete grants and a strict two-way field
  policy (`FieldProjection`).
- Capability manifest via the core lib (`CapabilityFactory`).
- Replay protection: `PdoNonceStore` (cluster-safe, DB unique constraint) and
  `FileNonceStore` (single host).
- Audit logging by field key (`FileAuditRecorder`).
- Cross-language interop pinned by `ContractSigningVectorTest`.

Host integration: subclass the four `web/` controllers once, implement
`composition()` (the app's composition root), and register the routes in
`app.ini`. See the canary app for a complete runnable example, including a local
mock TROPIKAL and a full OAuth + signed-job e2e script.

Deliberate scope: the package does not implement owner chat, workflows, MCP
tooling, or an AI agent inside n2n. Rocket CMF discovery was removed with the
legacy bridge model; a Rocket catalog can return as a separate provider package
if needed.
