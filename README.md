# Meros Foundations

A lean Laravel-in-WordPress integration layer for the Meros framework. It replaces `roots/acorn` with only the pieces Meros needs, built directly on the `illuminate/*` components (no `laravel/framework`, no vendored `Illuminate\Foundation`). It also owns `livewire/livewire` and `symfony/uid`.

```php
MM\Meros\Foundations\Foundations::boot(get_stylesheet_directory(), $providers, $stubDirectories);
```

## What it does

- **Application** – a small container-based application (eager providers, paths, `.env` lookup, config/package caches under `storage/framework`).
- **Config stubs** – `config/*.php` are WordPress-tuned defaults. Register more directories (`Foundations::boot(..., stubs: [...])` or `Stubs::register()`); later ones win. Layering order: Foundations stubs → registered stubs → the theme's own `config/`.
- **Bootstrap / HTTP** – lean kernel, WordPress-safe exception handling (only takes over in debug), `web`/`wordpress` middleware groups (cookies + sessions, no CSRF, as Acorn), `routes/web.php`.
- **Views** – Blade resolution ported from Acorn: namespace hints with theme `vendor/` overrides, `view.directives|components|composers`, auto-discovered `app/View/Composers`.
- **Package discovery** – `PackageManifest` scans the theme, content root and `meros/composer_paths` filter (`extra.meros` or `extra.laravel` providers).
- **WordPress runtime** – request capture and the `wordpress` catch-all route.

## WP-CLI

| Command | Subcommands / options |
| --- | --- |
| `wp meros:cache` | `clear [--store]`, `forget <key>` |
| `wp meros:views` | `clear`, `cache` |
| `wp meros:config` | `cache`, `clear` |
| `wp meros:optimize` | `run`, `clear` |
| `wp meros:key` | `generate [--show] [--force]` |
| `wp meros:publish` | `[<name>...] [--list] [--force]` copies Meros config stubs to the theme's `config/` |

## Not ported from Acorn

Multisite, `make:*`, vendor/generic publishing, queue, scheduler, pagination, the assets manifest provider, `skipProvider` error isolation.

## Testing

`php tests/boot.test.php`
