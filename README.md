# folk-laravel

Laravel adapter for Folk. Provides seamless integration -- all your routes, middleware, and controllers work without changes.

**Version:** 0.2.0

## Installation

```bash
composer require folk/laravel
```

Auto-discovery registers `FolkServiceProvider`, which auto-registers `LaravelHttpHandler` for HTTP dispatch.

## Requirements

- PHP 8.2+ (ZTS build)
- Laravel 11+
- [folk/sdk](https://github.com/Folk-Project/folk-sdk)
- `folk.so` extension

## Setup

1. Install the package:

```bash
composer require folk/laravel
```

2. Create `folk.toml` in your project root:

```toml
[workers]
script = "server.php"
count = 4

[http]
listen = "0.0.0.0:8080"
```

3. Create `server.php` entry point:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$loop = new \Folk\Sdk\Worker\WorkerLoop();
$loop->registerHttpHandler(
    $app->make(\Folk\Laravel\Http\LaravelHttpHandler::class)
);
$loop->run();
```

4. Build and run:

```bash
folk-builder build
./my-folk serve
```

## How it works

- `FolkServiceProvider` registers `LaravelHttpHandler` in the container
- `LaravelHttpHandler` converts Folk's request arrays (received as native PHP arrays via zval) into Laravel `Request` objects
- After the Laravel kernel processes the request, the response is converted back to a Folk response array
- No JSON encode/decode -- direct zval arrays between Rust and PHP

## State resetters

Between every request, built-in resetters clean up shared state:

| Resetter | What it does |
|----------|-------------|
| `AuthResetter` | Forgets authenticated user |
| `DatabaseResetter` | Rolls back open transactions |
| `EventResetter` | Clears request-scoped listeners |
| `QueueResetter` | Reconnects queue connections |

## License

MIT
