# TROPIKAL Connect n2n Spec

`tropikal-ai/connect-n2n` connects a Rocket-powered n2n site to TROPIKAL Connect.

The package discovers Rocket entities from `Rocket::getSpec()` and maps EiTypes, EiMasks, EiProps, and command metadata into a safe entity manifest. Rocket admins grant Read, Write, and Delete access per entity. Empty grants expose nothing.

The bridge endpoint executes normalized operations:

- `rocket.entity.list`
- `rocket.entity.get`
- `rocket.entity.create`
- `rocket.entity.update`
- `rocket.entity.delete`

Each request must be signed with the canonical request format from `tropikal-ai/connect`, including method, path, normalized query string, timestamp, nonce, body hash, and installation ID.

Owner chat, workflow functions, and MCP/tool exposure are backend responsibilities and are intentionally absent from this package.
