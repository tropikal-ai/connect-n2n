# Changelog

## Unreleased

### Added
- **One-click connect (OAuth 2.1 + PKCE)** mirroring the Filament adapter:
  `ConnectFlow` (begin/complete), dynamic client registration, control-plane
  installation registration, and `ConnectAdminController` with a status page.
- **Encrypted-at-rest state**: `EncryptedFileInstallationStore` (AES-256-GCM,
  HKDF-SHA256, per-write salt/IV, fail-closed).
- **Cluster-safe replay protection**: `PdoNonceStore` (DB unique-constraint
  claims).
- **Reusable n2n controllers** in `web/` (Resource/Schema/Health/Admin +
  `ConnectControllerBase`, `AdminGate`).
- **Filament-modeled resource API**: `ResourceApi` with RESTful
  list/get/create/update/delete + `/schema`, per-resource
  read/create/update/delete grants, capability manifest via the core lib
  (`risk_level`, JSON schemas), pagination/search.
- **Contract interop test**: byte-for-byte assertion of the shared
  `signed-request.vector.json`.
- GitHub Actions CI (PHP 8.2/8.3/8.4: Pint, PHPStan, PHPUnit + coverage).

### Changed
- Rebuilt as a strict DDD package: `domain/` (pure), `application/`,
  `infrastructure/`, `web/`. Composer type changed `n2n-module` → `library`.
- `Installation::withGrant()` is now symmetric: dropping the last permission
  un-exposes the resource.

### Removed
- **The legacy bridge model** (~2,100 LOC): `rocket.entity.*` bridge executor,
  Rocket `getSpec()` discovery, bespoke manifest/DTOs/bo entities, plaintext
  state file, and the old plain-class controllers. The wire surface is now the
  Filament-style resource API.

## 0.1.0

- Initial release candidate for the n2n Rocket CMF integration.
- Adds Rocket entity discovery, per-entity Read/Write/Delete grants, signed
  bridge execution, local encrypted state storage, nonce replay protection, and
  audit logging.
