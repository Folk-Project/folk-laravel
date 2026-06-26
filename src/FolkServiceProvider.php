<?php declare(strict_types=1);
namespace Folk\Laravel;

use Folk\Sdk\Grpc\GrpcRouter;
use Folk\Sdk\Worker\HandlerLoop;
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

        // Only register worker hooks when running as a Folk worker
        if (!function_exists('folk_worker_run')) {
            return;
        }

        // Register artisan commands
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

            // Resetters — run between requests
            $loop->registerResetter(new Reset\AuthResetter($app));
            $loop->registerResetter(new Reset\DatabaseResetter($app));
            $loop->registerResetter(new Reset\EventResetter($app));
            $loop->registerResetter(new Reset\QueueResetter($app));
            $loop->registerResetter(new \Folk\Sdk\Reset\TempUploadResetter());
        };
    }
}
