# Security

Report security issues privately to the maintainers. Do not open public issues for vulnerabilities.

Security rules for this package:

- Rocket setup routes must require an authenticated Rocket admin or superadmin.
- Bridge calls must be signed with `tropikal-ai/connect` canonical request signatures.
- Nonces must be claimed atomically and rejected on replay.
- Default entity access is none.
- Read, Write, and Delete grants are separate.
- Browser and admin payloads must not contain secrets or secret-shaped keys.
- Local signing secrets must be stored encrypted.
- Mutations must be audit logged with redacted payloads.

This package does not implement MCP, workflow execution, owner chat, or an AI agent.
