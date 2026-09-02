# folk-laravel

Laravel adapter for Folk. Provides seamless integration -- all your routes, middleware, and controllers work without changes.

## Installation

```bash
composer require folk/laravel
```

Auto-discovery registers `FolkServiceProvider`, which auto-registers `LaravelHttpHandler` for HTTP dispatch.

## Requirements

- PHP 8.2+ — a standard non-thread-safe (NTS) build; Folk forks worker processes, so no thread-safe PHP is needed
- Laravel 11+
- [folk/sdk](https://github.com/Folk-Project/folk-sdk)
- the `folk.so` extension (`pie install folk-project/ext-folk`)

## Setup

1. Install the package:

```bash
composer require folk/laravel
```

2. Create `folk.toml` in your project root:

```toml
[workers]
script = "vendor/bin/folk-server"
count = 4

[http]
listen = "0.0.0.0:8080"
public_dir = "public"   # serve built assets from disk; a miss falls through to Laravel
```

3. Run:

```bash
php vendor/bin/folk-server
```

The entry point ships with this package (`bin/folk-server`) -- there is no worker
script to write. Composer's bin-proxy (`vendor/bin/folk-server`) works as well.

## How it works

- `FolkServiceProvider` registers `LaravelHttpHandler` in the container
- `LaravelHttpHandler` converts Folk's request arrays (received as native PHP arrays via zval) into Laravel `Request` objects
- After the Laravel kernel processes the request, the response is converted back to a Folk response array
- No JSON encode/decode -- direct zval arrays between Rust and PHP

## State resetters

Between every request, built-in resetters clean up shared state:

| Resetter | What it does |
|----------|-------------|
| `AuthResetter` | Forgets the authenticated user and all resolved guards |
| `SessionResetter` | Drops the request's session instance |
| `DatabaseResetter` | Rolls back open transactions |
| `EventResetter` | Clears request-scoped listeners |
| `QueueResetter` | Reconnects queue connections |
| `ScopedResetter` | Forgets the container's `scoped` instances |
| `InertiaResetter` | Clears Inertia's shared props |

Register your own via `config/folk.php`:

```php
'resetters' => [
    App\Folk\MyStateResetter::class, // implements Folk\Sdk\Reset\ResettableInterface
],
```

## License

MIT — see [LICENSE](LICENSE).
