# folk-laravel

Laravel integration for Folk — HTTP handler, jobs queue driver, gRPC router, state resetters, and fork mode support.

## Requirements

- PHP 8.2+
- Laravel 11+
- [folk/sdk](https://github.com/Folk-Project/folk-sdk)

## Installation

```bash
composer require folk/laravel
```

Auto-discovery registers `FolkServiceProvider` automatically.

## Quick start

1. Configure `folk.toml`:

```toml
[server]
runtime = "pipe"

[workers]
script = "vendor/bin/folk-worker"
count = 4

[http]
listen = "0.0.0.0:8080"
```

2. Build and start Folk:

```bash
folk-builder build
./folk-server
```

All your Laravel routes, middleware, and controllers work out of the box.

## Full `folk.toml` reference

```toml
# --- Server ---
[server]
runtime = "pipe"              # "pipe" or "fork" (fork requires ext-pcntl + ext-sockets)
rpc_socket = "./tmp/folk.sock" # Unix socket for admin RPC (jobs push, artisan commands)
shutdown_timeout = "30s"       # Grace period after SIGTERM before force-kill

# --- Workers ---
[workers]
script = "vendor/bin/folk-worker"  # PHP worker entry point
php = "php"                        # Path to PHP binary
count = 4                          # Number of worker processes
max_jobs = 1000                    # Recycle worker after N requests
ttl = "1h"                         # Recycle worker after this duration
max_memory_mb = 256                # Recycle worker exceeding this RSS (MB)
exec_timeout = "30s"               # Per-request execution timeout
boot_timeout = "30s"               # Max time to wait for worker boot

# --- HTTP plugin ---
[http]
listen = "0.0.0.0:8080"       # Address and port
read_timeout = "10s"           # Max time to read request body
write_timeout = "30s"          # Max time to write response

# --- Jobs plugin ---
[jobs]
driver = "redis"                       # "memory" (dev only) or "redis"
redis_url = "redis://127.0.0.1:6379"   # Redis connection (redis driver only)

[[jobs.queues]]
name = "default"               # Queue name
concurrency = 4                # Concurrent consumers for this queue
max_retries = 3                # Max retry attempts before discarding

[[jobs.queues]]
name = "emails"
concurrency = 2

# --- gRPC plugin ---
[grpc]
listen = "0.0.0.0:50051"      # Address and port for gRPC server
proto = ["./proto/service.proto"]  # Proto files for gRPC reflection (auto-resolves imports)

# --- Metrics plugin ---
[metrics]
listen = "0.0.0.0:9090"       # Address for /metrics and /health endpoints

# --- Process supervisor plugin ---
[[process]]
name = "vite"
command = "npx vite"
restart = "always"             # "always", "on_failure", "never"
max_restarts = 5
restart_delay = "1s"

# --- Logging ---
[log]
filter = "info"                # Log level: "debug", "info", "warn", "error"
format = "json"                # "text", "json", "pretty"
```

Duration format: `"500ms"`, `"10s"`, `"5m"`, `"1h"`.

Environment variable overrides: prefix with `FOLK_`, e.g. `FOLK_WORKERS_COUNT=8`.

## Laravel configuration

`config/folk.php`:

```php
return [
    // Unix socket for RPC communication (jobs push, admin commands)
    'rpc_socket' => './tmp/folk.sock',

    // gRPC service registration
    'grpc' => [
        'services' => [
            // \App\Proto\Greeter\GreeterInterface::class => \App\Grpc\GreeterService::class,
        ],
    ],
];
```

## HTTP

Works automatically. All Laravel routes are served through Folk:

```php
// routes/web.php — no changes needed
Route::get('/ping', fn () => response()->json(['status' => 'ok']));
Route::resource('users', UserController::class);
```

State is reset between requests (auth, DB transactions, events, queue) via built-in resetters.

## Jobs

Folk provides a Laravel Queue driver. Jobs go through Folk's RPC — your app doesn't touch Redis directly.

### Setup

1. Add `folk` connection to `config/queue.php`:

```php
'connections' => [
    'folk' => [
        'driver' => 'folk',
        'queue' => 'default',
    ],
    // ...
],
```

2. Set in `.env`:

```env
QUEUE_CONNECTION=folk
```

3. Configure queues in `folk.toml`:

```toml
[jobs]
driver = "redis"                      # or "memory" for dev
redis_url = "redis://127.0.0.1:6379"

[[jobs.queues]]
name = "default"
concurrency = 4

[[jobs.queues]]
name = "emails"
concurrency = 2
```

### Dispatching

Standard Laravel dispatch — no changes:

```php
SendEmail::dispatch($user, $subject);
SendEmail::dispatch($user, $subject)->onQueue('emails');
```

Flow:
```
dispatch() → FolkQueue → RPC (jobs.push) → folk-plugin-jobs → [memory|redis] → PHP worker → fire()
```

### Job classes

Standard Laravel jobs work out of the box:

```php
class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly User $user) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to($this->user->email)->send(new WelcomeMail($this->user));
    }
}
```

## gRPC

Folk routes gRPC calls to PHP handlers with typed protobuf support.

### Setup

1. Define your proto and generate PHP classes:

```bash
protoc --php_out=app/Proto proto/greeter.proto
```

2. Create a service interface (or use `protoc-gen-php-grpc`):

```php
<?php
namespace App\Proto\Greeter;

use Folk\Sdk\Grpc\ServiceInterface;

interface GreeterInterface extends ServiceInterface
{
    public const NAME = "greeter.Greeter";

    public function SayHello(HelloRequest $in): HelloReply;
}
```

3. Implement the service:

```php
<?php
namespace App\Grpc;

use App\Proto\Greeter\{GreeterInterface, HelloReply, HelloRequest};

class GreeterService implements GreeterInterface
{
    public function SayHello(HelloRequest $in): HelloReply
    {
        $reply = new HelloReply();
        $reply->setMessage("Hello, {$in->getName()}!");
        return $reply;
    }
}
```

4. Register in `config/folk.php`:

```php
'grpc' => [
    'services' => [
        \App\Proto\Greeter\GreeterInterface::class => \App\Grpc\GreeterService::class,
    ],
],
```

5. Enable in `folk.toml`:

```toml
[grpc]
listen = "0.0.0.0:50051"
```

### Metadata (auth tokens, headers)

Add `Context` as the first parameter:

```php
use Folk\Sdk\Grpc\Context;

class GreeterService implements GreeterInterface
{
    public function SayHello(Context $ctx, HelloRequest $in): HelloReply
    {
        $token = $ctx->getValue('authorization');
        $requestId = $ctx->getValue('x-request-id');
        // ...
    }
}
```

### Spiral/RoadRunner compatibility

Services generated by `protoc-gen-php-grpc` (Spiral) work with Folk:

```php
'services' => [
    InfoInterface::class => InfoService::class, // Spiral-generated interface
],
```

The `NAME` constant is read from any interface. Context parameters receive Folk's `Context` instance.

## Fork mode

For heavy applications, fork mode boots Laravel once and forks workers from the warm state:

```toml
[server]
runtime = "fork"
```

Benefits:
- Workers skip framework bootstrap (warm OPcache via copy-on-write)
- DB connections are automatically reconnected after fork

Requires: `ext-pcntl`, `ext-sockets`.

## State resetters

Between every request, these resetters clean up shared state:

| Resetter | What it does |
|----------|-------------|
| `AuthResetter` | Forgets authenticated user on all guards |
| `DatabaseResetter` | Rolls back open transactions |
| `EventResetter` | Clears request-scoped listeners |
| `QueueResetter` | Reconnects queue connections |

## Artisan commands

| Command | Description |
|---------|-------------|
| `folk:reload` | Gracefully recycle all workers |
| `folk:workers` | Show worker pool status |

## License

MIT
