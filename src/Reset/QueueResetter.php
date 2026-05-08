<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Illuminate\Contracts\Foundation\Application;

final class QueueResetter
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        // Reconnect queue connections to avoid stale connection issues
        try {
            $this->app->make('queue')->connection()->reconnect();
        } catch (\Throwable) {}
    }
}
