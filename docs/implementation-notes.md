# Implementation Notes

This release candidate implements the Connect/Rocket boundary conservatively.

Implemented:

- Composer `n2n-module` package metadata.
- Rocket admin authorization guard using the `LoginContext` shape.
- Safe connection state model and encrypted file-backed state store.
- Atomic file-backed nonce store.
- Rocket entity discovery through `Rocket::getSpec()` compatible adapters.
- EiType/EiMask/EiProp/EiCmd mapping into safe entity descriptors.
- Per-entity Read, Write, and Delete grants.
- Generated entity manifest for TROPIKAL backend import.
- Signed bridge verification with `tropikal-ai/connect`.
- Structured bridge execution and errors.
- Redacted audit logging.

Intentionally conservative:

- Concrete Rocket persistence execution is behind `RocketEntitySearcher`, `RocketEntityReader`, `RocketEntityWriter`, and `RocketEntityDeleter`.
- The default adapter returns structured `unsupported_operation` responses.
- A production Rocket app should bind these interfaces to the proven Rocket persistence APIs for its version before enabling write/delete operations.
- Publishing is not exposed until a safe Rocket draft/publish API and explicit approval metadata are wired and tested.

The package does not implement owner chat, workflow execution, MCP tooling, or an AI agent inside n2n.
