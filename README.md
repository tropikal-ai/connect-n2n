# TROPIKAL Connect for n2n

`tropikal-ai/connect-n2n` is the native [n2n](https://www.n2n.rocks) integration
for **TROPIKAL Connect**. It lets an n2n site connect to TROPIKAL with one click
and exposes the site's entities to TROPIKAL jobs through a **signed, RESTful
resource API** — the same capability the Filament adapter provides, built the
same way.

It works with plain n2n ORM entities; there is no CMS dependency.

## What it does

- **One-click connect** — OAuth 2.1 + PKCE authorization, dynamic client
  registration, and control-plane installation registration. No hand-copied
  secrets; the signing key is issued by the control plane and stored encrypted.
- **Signed resource API** — a TROPIKAL job reads and writes granted resources:
  `GET/POST/PUT/DELETE /resources/{slug}`, `GET /schema`. Every request is
  HMAC-SHA256 verified (timestamp + single-use nonce) via `tropikal-ai/connect`.
- **Per-resource grants** — read / create / update / delete, exposed as a
  capability manifest (`risk_level`, JSON input/output schemas).
- **Safe by construction** — only explicitly declared fields are readable or
  writable; secrets are AES-256-GCM encrypted at rest; mutations are audit
  logged by field key, never value.

## Architecture (DDD)

```
domain/          pure model + rules, zero framework code
  resource/      ResourceSpec, FieldSpec, Operation, ListQuery
  grant/         Permission
  installation/  Installation (aggregate)
  oauth/         PendingAuthorization
  service/       FieldProjection, CapabilityFactory
  port/          ResourceStore, ResourceCatalog, InstallationStore,
                 AuthorizationServerGateway, ControlPlaneGateway, AuditRecorder
application/     use cases: ResourceApi, SignedRequestGuard, ConnectFlow
infrastructure/  adapters: N2nResourceStore/N2nOrmSession, Http* gateways,
                 EncryptedFileInstallationStore, FileNonceStore, FileAuditRecorder
web/             reusable n2n ControllerAdapters (Resource/Schema/Health/Admin)
```

The domain and application layers never import n2n or the transport; the single
seam that touches the n2n `EntityManager` is `infrastructure/orm/N2nOrmSession`.

## Install

```bash
composer require tropikal-ai/connect-n2n
```

For local path development, add a `path` repository pointing at this package and
`composer require tropikal-ai/connect-n2n:@dev`.

## Wire it into an n2n app

Subclass the four controllers once and implement `composition()` — your
composition root — declaring the resources you expose, their ORM bindings, and
where TROPIKAL lives:

```php
class ResourceController extends \tropikal\connect\n2n\web\ResourceController {
    protected function composition(N2nContext $n2nContext): ConnectComposition {
        $em = $n2nContext->lookup(EntityManager::class);
        $catalog = new StaticResourceCatalog(
            new ResourceSpec('article', 'Articles', [
                new FieldSpec('title', 'string', writable: true, required: true),
                new FieldSpec('categoryId', 'integer', writable: true),
            ]),
            /* ...category... */
        );
        $store = new EncryptedFileInstallationStore($statePath, $encryptionKey);
        // build ResourceApi, SignedRequestGuard, ConnectFlow → ConnectComposition
    }
}
```

Register the routes in `var/etc/<module>/app.ini`:

```ini
[routing]
controllers[/connect/admin]     = "app\connect\controller\AdminController"
controllers[/connect/resources] = "app\connect\controller\ResourceController"
controllers[/connect/schema]    = "app\connect\controller\SchemaController"
controllers[/connect/health]    = "app\connect\controller\HealthController"
```

Then open `/connect/admin`, click **Connect**, and approve. See the
[canary app](../canary/app/connect) for a complete, runnable example including a
signed-job end-to-end script (`bin/connect-e2e.php`).

## Native n2n integration

The package leans on the framework rather than around it:

- **Routing** — `ResourceController` declares its REST routes natively in
  `_annos()` via `AnnoPath` patterns gated per HTTP verb
  (`AnnoGet`/`AnnoPost`/`AnnoPut`/`AnnoDelete`), and the admin surface uses
  verb-prefixed do-methods (`getDoConnect`, `getDoCallback`). Supported verbs
  are GET/POST/PUT/DELETE (n2n has no PATCH dispatch).
- **Binding** — write payloads and list query parameters pass through
  [n2n-bind](https://docs.n2n.rocks/docs/n2n-bind/install): each declared field
  gets type-appropriate mappers (`cleanString`, ranged ints, strict bools), and
  the payload fails closed with stable wire codes (`unknown_fields`,
  `missing_required`, `invalid_fields`).
- **Errors** — the human admin surface throws n2n's typed
  `ForbiddenException`; the signed API keeps its JSON error envelope (that is
  the TROPIKAL wire contract).
- **Tests** — `tests/integration` boots a real `N2nContext` through
  `n2n/n2n-test` and drives routed HTTP requests through the annotations,
  guard, bind, and API — the same pipeline production serves.

## Development

```bash
composer install
vendor/bin/pint --test      # style
vendor/bin/phpstan analyse  # static analysis
vendor/bin/phpunit          # tests (incl. the cross-language signing vector)
```

## License

MIT.
