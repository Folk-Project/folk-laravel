<?php declare(strict_types=1);
namespace Folk\Laravel;

use Folk\Sdk\Grpc\GrpcRouter;
use Folk\Sdk\Reset\ResettableInterface;
use Folk\Sdk\Worker\HandlerLoop;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class FolkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/folk.php', 'folk');

        // Register Folk queue connector (available even outside Folk workers for dispatch)
        $this->app->afterResolving('queue', function ($manager): void {
            $manager->addConnector('folk', fn () => new Queue\FolkConnector());
        });

        // Code generation is a build-time tool — available in any `php artisan`
        // run, not only inside a Folk worker process.
        if ($this->app->runningInConsole()) {
            $this->commands([Console\GenerateGrpcCommand::class]);
        }

        // Only register worker hooks when running as a Folk worker
        if (!function_exists('folk_worker_run')) {
            return;
        }

        // Worker-management artisan commands (need a running Folk process)
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ReloadCommand::class,
                Console\WorkersCommand::class,
            ]);
        }

        // Register boot hook — called before WorkerLoop::run()
        $GLOBALS['folk_worker_boot_hook'] = function (HandlerLoop $loop): void {
            $app = $this->app;

            // Stamp request_id onto application logs for correlation with Folk's
            // Rust-side access log. Pushed onto the default channel's Monolog
            // logger; reads the id at log time, so nothing to reset between requests.
            $channel = $app->make(\Illuminate\Log\LogManager::class)->channel();
            if ($channel instanceof \Illuminate\Log\Logger) {
                $monolog = $channel->getLogger();
                if ($monolog instanceof \Monolog\Logger) {
                    $monolog->pushProcessor(new Log\FolkRequestIdProcessor());
                }
            }

            // HTTP handler
            $loop->registerHttpHandler(new Handler\LaravelHttpHandler(
                $app,
                (int) config('folk.streaming.max_request_bytes', 0),
                (array) config('folk.streaming.limits', []),
            ));

            // Jobs handler
            $loop->registerJobsHandler(new Queue\FolkJobHandler());

            // gRPC handler (if services configured)
            $grpcServices = config('folk.grpc.services', []);
            if ($grpcServices !== []) {
                $router = new GrpcRouter();
                foreach ($grpcServices as $name => $class) {
                    $router->register($name, $app->make($class));
                }
                $loop->registerGrpcHandler($router);
            }

            // Resetters — run between requests. Built-in + app-registered.
            foreach (self::resetters($app) as $resetter) {
                $loop->registerResetter($resetter);
            }
        };
    }

    /**
     * The per-request resetters registered for a Folk worker: the built-in set
     * plus any classes listed in `config('folk.resetters')`.
     *
     * `ScopedResetter` flushes the container's scoped instances and
     * `InertiaResetter` flushes Inertia's shared props — the Octane-parity
     * fixes that stop a persistent worker replaying the first request's state.
     * `SessionResetter` flushes the cached session store and `AuthResetter`
     * drops resolved guards so auth/session state can't leak between requests
     * (folk-releases #86); Session runs before Auth so the guard is dropped
     * against an already-cleared store.
     *
     * @return list<ResettableInterface>
     */
    public static function resetters(Application $app): array
    {
        $resetters = [
            new Reset\SessionResetter($app),
            new Reset\AuthResetter($app),
            new Reset\DatabaseResetter($app),
            new Reset\EventResetter($app),
            new Reset\QueueResetter($app),
            new \Folk\Sdk\Reset\TempUploadResetter(),
            new Reset\ScopedResetter($app),
            new Reset\InertiaResetter($app),
        ];

        foreach ((array) config('folk.resetters', []) as $class) {
            if (!is_string($class) || $class === '') {
                continue;
            }
            try {
                $resetter = $app->make($class);
            } catch (\Throwable $e) {
                error_log("Folk: cannot resolve resetter {$class}: " . $e->getMessage());
                continue;
            }
            if ($resetter instanceof ResettableInterface) {
                $resetters[] = $resetter;
            } else {
                error_log("Folk: resetter {$class} must implement " . ResettableInterface::class);
            }
        }

        return $resetters;
    }
}
