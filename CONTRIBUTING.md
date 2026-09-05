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

### A local pass doesn't guarantee a CI pass

CI runs `composer update --prefer-stable` fresh on every job, not `composer install`
against the committed lock file. That's deliberate: it tests this package against the
latest resolvable dependency versions across the full PHP and Laravel matrix, not just
whatever happens to be sitting in `vendor/` on your machine.

This has actually broken a push before: adding `phpstan/phpstan-deprecation-rules` passed
`composer stan` locally, then failed on all 11 CI matrix jobs, because CI's fresh install
pulled a newer `larastan` that caught something the locally-cached older version didn't.

If your change touches `composer.json`, or anything that could interact with a dependency
bump (deprecation annotations, type hints against SDK classes), run `composer update`
locally before trusting a clean `composer stan`/`composer test`. A pass against your
current `vendor/` isn't proof CI will pass.

## What this package does and doesn't do

- It delegates all HTTP transport to the official `sentdm/sent-dm-php` SDK. It never
  makes a raw HTTP call itself. If a feature needs an endpoint the installed SDK doesn't
  expose, it can't be added here yet, it needs the SDK to add it first.
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
