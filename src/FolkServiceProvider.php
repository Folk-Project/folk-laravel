<?php declare(strict_types=1);
namespace Folk\Laravel;

use Folk\Sdk\Worker\WorkerLoop;
use Illuminate\Support\ServiceProvider;

final class FolkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only register when running as a Folk worker
        if (!getenv('FOLK_RUNTIME')) {
            return;
        }

        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/folk.php', 'folk');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ReloadCommand::class,
                Console\WorkersCommand::class,
            ]);
        }

        // Register boot hook — called before WorkerLoop::run()
        $GLOBALS['folk_worker_boot_hook'] = function (WorkerLoop $loop): void {
            $app = $this->app;

            // After fork: reconnect DB to avoid sharing parent's PDO handles
            if (getenv('FOLK_RUNTIME') === 'fork') {
                $db = $app->make('db');
                foreach ($db->getConnections() as $connection) {
                    $connection->reconnect();
                }
            }

            // HTTP handler
            $loop->registerHttpHandler(new Handler\LaravelHttpHandler($app));

            // Resetters — run between requests
            $loop->registerResetter(new Reset\AuthResetter($app));
            $loop->registerResetter(new Reset\DatabaseResetter($app));
            $loop->registerResetter(new Reset\EventResetter($app));
            $loop->registerResetter(new Reset\QueueResetter($app));
        };

        // Fork-mode master boot hook (warms OPcache, autoloader, framework state)
        $GLOBALS['folk_master_boot_hook'] = function (): void {
            $this->app->boot();
        };
    }
}
