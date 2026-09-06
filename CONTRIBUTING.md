# Contributing

Thanks for looking at this package. A few things that keep it consistent.

## Before you open a PR

```bash
composer install
composer lint          # syntax check
composer format        # Pint, Laravel preset
composer stan           # PHPStan, level max
composer test           # Pest, 100% coverage required
```

All four run in CI on every PR. A PR that fails any of them won't merge.

## What this package does and doesn't do

- It delegates HTTP transport to the official `sentdm/sent-dm-php` SDK wherever the SDK
  has a typed method for the endpoint. The one sanctioned exception is
  `Resource::raw()`, which calls the SDK's own generic `Client::request()` for an
  endpoint that exists on Sent.dm's live API but has no typed method in any published
  SDK version yet (see `SenderProfiles`, `Channels`, `Compliance`). It's still the SDK's
  transport, auth, and retries, just without a generated request/response class. Every
  other method traces to a real typed SDK call; `raw()` isn't a general escape hatch for
  convenience.
- Resource, builder, and query-builder classes are immutable: every setter returns a
  clone, nothing is mutated in place.
- No facades inside the package's internals. Config is injected through constructors,
  never read via `config()` inside the SDK adapter layer.
- Exceptions are never caught silently. They bubble, or get dispatched as a typed event.

## Adding or changing wrapper methods

Every method this package exposes has to trace to a real method on the installed SDK.
Two scripts enforce this and run in CI:

```bash
php .github/scripts/method_audit.php   # wrapper calls vs installed SDK methods/params
php .github/scripts/shape_audit.php    # hand-built arrays vs installed SDK response models
```

Run both locally before opening a PR that touches `src/Resources/` or `src/Builders/`.

## Tests

Pest 3, Orchestra Testbench. `Http::fake()` for every API call, no live network in tests.
No `pestphp/pest-plugin-laravel`: not needed, and not compatible with Laravel 13, the
newest version this package supports.

## Reporting a security issue

See [SECURITY.md](SECURITY.md).
