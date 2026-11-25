# Meros Foundations

A lean Laravel-in-WordPress integration layer for the Meros framework. It replaces `roots/acorn` with only the pieces Meros needs, built directly on the `illuminate/*` components (no `laravel/framework`, no vendored `Illuminate\Foundation`). It also owns `livewire/livewire` and `symfony/uid`.

```php
MM\Meros\Foundations\Foundations::boot(get_stylesheet_directory(), $providers, $stubDirectories);
```

## What it does

- **Application** – a small container-based application (eager providers, including providers registered during boot, paths, `.env` lookup, config/package caches under `storage/framework`).
- **Config stubs** – `config/*.php` are WordPress-tuned defaults. Register more directories (`Foundations::boot(..., stubs: [...])` or `Stubs::register()`); later ones win. Layering order: Foundations stubs → registered stubs → the theme's own `config/`.
- **Bootstrap / HTTP** – lean kernel, WordPress-safe exception handling (only takes over in debug), `web`/`wordpress` middleware groups (cookies + sessions, no CSRF, as Acorn), `routes/web.php`.
- **Views** – Blade resolution ported from Acorn: namespace hints with theme `vendor/` overrides, `view.directives|components|composers`, auto-discovered `app/View/Composers`.
- **Queue, auth, hashing, bus** – database/sync queues with `Artisan::call('queue:work')`, session/Eloquent auth and the password broker. `Illuminate\Foundation\Auth\User` resolves to `MM\Meros\Foundations\Auth\User`, so packages written against Laravel keep working. Config stubs: `auth`, `queue`.
- **Package discovery** – `PackageManifest` scans the theme, content root and `meros/composer_paths` filter two lists: `extra.laravel.providers` (Laravel packages such as Livewire, registered by Foundations) and `extra.meros.provider`/`providers` (Meros packages, registered by the framework once its provider is up). `dont-discover` is honoured under either key. The cache rebuilds itself whenever a `composer.json` or `installed.json` is newer, so no composer hook is needed.
- **WordPress runtime** – request capture and the `wordpress` catch-all route.

## Paths and storage

Storage resolves to the theme's `storage/` directory when it exists, otherwise `wp-content/cache/meros`. Any of `app`, `bootstrap`, `config`, `database`, `lang`, `public`, `resources`, `storage` can be overridden with a `MEROS_{TYPE}_PATH` env var or constant (relative to the theme).

## WP-CLI

Console bootstrapping binds a synthetic request using `app.url` before providers load, so request-dependent services are available to WP-CLI commands.

| Command | Subcommands / options |
| --- | --- |
| `wp meros:cache` | `clear [--store]`, `forget <key>` |
| `wp meros:views` | `clear`, `cache` |
| `wp meros:config` | `cache`, `clear` |
| `wp meros:optimize` | `run`, `clear` |
| `wp meros:key` | `generate [--show] [--force]` |
| `wp meros:queue` | `work`, `restart`, `retry`, `failed`, `forget`, `flush`, `clear` (wraps Laravel's `queue:*`; e.g. `wp meros:queue work <connection> --queue=x --stop-when-empty`) |
| `wp meros:publish` | `[<name>...] [--package=<name>] [--provider=<class>] [--all] [--tag=<tag>] [--list] [--force]` copies Meros config stubs, or the config a Laravel/Meros package declares with `publishes()`, to the theme's `config/` (other destinations are ignored) |

## Not ported from Acorn

Multisite, `make:*`, generic (non-config) publishing, queue, scheduler, pagination, the assets manifest provider, `skipProvider` error isolation.

## Testing

`php tests/boot.test.php`
