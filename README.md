# Meros Foundations

A lean Laravel-in-WordPress integration layer for the Meros framework. It replaces `roots/acorn` with only the pieces Meros needs, on top of `laravel/framework`.

```php
MM\Foundations\Foundations::boot(get_stylesheet_directory(), $providers, $stubDirectories);
```

## What it does

- **Application** – Laravel's application with WordPress-aware `.env` lookup and maintenance mode.
- **Config stubs** – `config/*.php` are WordPress-tuned defaults. Register more directories (`Foundations::boot(..., stubs: [...])` or `Stubs::register()`); later ones win. Layering order: Laravel defaults → Foundations stubs → registered stubs → the application's own `config/`. `Stubs::publish($dir)` copies the winning stubs out.
- **Bootstrap** – HTTP and console kernels with a WordPress-safe exception bootstrapper (only takes over in debug).
- **Package discovery** – `PackageManifest` scans the theme, content root and `meros/composer_paths` filter (needed for Livewire auto-discovery).
- **WordPress runtime** – request capture, `wordpress` catch-all route, and `wp meros <command>` for WP-CLI.

## Not (yet) ported from Acorn

Blade composers/components auto-discovery, multisite cache/session/queue isolation, `acorn:init`/vendor publish commands, the assets manifest provider, WP-aware pagination, `skipProvider` error isolation.

## Testing

`php tests/boot.test.php`
