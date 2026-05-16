# Contributing

This package is intentionally small. Keep protocol code in `tropikal-ai/connect` and Rocket-specific code in this package.

Before opening a change, run:

```bash
composer validate --strict
composer install --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/phpunit --colors=never
```

Do not add hand-entered token setup, copied secrets, browser-visible credentials, MCP server behavior, or AI-agent behavior to this package.
