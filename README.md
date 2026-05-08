# folk-laravel

Laravel adapter for Folk — HTTP handler, state resetters, and Artisan commands.

> **Status:** in active development. See [folk-spec](https://github.com/Folk-Project/folk-spec) for the roadmap.

## Requirements

- PHP 8.2+
- Laravel 11+
- [folk/sdk](https://github.com/Folk-Project/folk-sdk)

## Installation

```bash
composer require folk/laravel
```

The package uses Laravel auto-discovery — no manual provider registration needed.

Publish the configuration:

```bash
php artisan vendor:publish --tag=folk-config
```

This creates `config/folk.php`:

```php
<?php

return [
    'rpc' => env('FOLK_RPC', 'tcp://127.0.0.1:6001'),
];
```

## Quick start

1. Install the package:

```bash
composer require folk/laravel
```

2. Set the environment variable to activate the provider:

```env
FOLK_RUNTIME=1
```

3. Configure `folk.toml` to point at your Laravel app:

```toml
[workers]
script = "vendor/bin/folk-worker"
count = 4
```

4. Start Folk:

```bash
./my-folk serve
```

Laravel's HTTP kernel handles all incoming requests automatically.

## Configuration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `folk.rpc` | `String` | `"tcp://127.0.0.1:6001"` | Admin RPC address for Artisan commands. Set via `FOLK_RPC` env var. |

## FolkServiceProvider

The provider **only activates when `FOLK_RUNTIME` is set** in the environment. When running via `php artisan serve` or any non-Folk context, the provider returns early and does nothing. This prevents interference with normal Laravel operations.

When active, the provider:

1. Merges `config/folk.php` into the application config.
2. Registers Artisan commands (`folk:reload`, `folk:workers`).
3. Sets up a **worker boot hook** that:
   - Registers `LaravelHttpHandler` with the `WorkerLoop` (handles `http.handle` RPC calls)
   - Registers four state resetters that run between every request

## Artisan commands

**`php artisan folk:reload`** — Gracefully recycle all worker processes. Workers finish in-flight requests before restarting.

**`php artisan folk:workers`** — Show current worker pool status.

## State resetters

Long-lived workers accumulate state between requests. The package registers four resetters that clean up after each request:

| Resetter | What it does |
|----------|-------------|
| `AuthResetter` | Calls `forgetUser()` on all auth guards. |
| `DatabaseResetter` | Rolls back any open database transactions. |
| `EventResetter` | Clears request-scoped event listeners. |
| `QueueResetter` | Reconnects queue connections to prevent stale handles. |

## How it works

When Folk starts a PHP worker with `FOLK_RUNTIME=1`:

1. Laravel boots normally via the service provider.
2. The worker boot hook registers `LaravelHttpHandler` with `folk-sdk`'s `WorkerLoop`.
3. For each incoming HTTP request:
   - Folk sends an `http.handle` RPC call with the serialized request.
   - `LaravelHttpHandler` converts it to a Symfony `Request`.
   - The request passes through Laravel's HTTP kernel.
   - The response is converted back and returned to Folk.
4. After each request, all resetters run to clean up auth state, database transactions, event listeners, and queue connections.

For fork runtime mode, a **master boot hook** bootstraps the full Laravel application once. Forked workers inherit the warm application state.

## License

MIT
