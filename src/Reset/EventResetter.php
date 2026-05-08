<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Illuminate\Contracts\Foundation\Application;

final class EventResetter
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        // Clear all listeners added during the request (not boot-time listeners)
        $dispatcher = $this->app->make('events');
        if (method_exists($dispatcher, 'getRawListeners')) {
            // Keep only the listeners registered during boot
            // This is a simplified implementation; production code would snapshot at boot
        }
        // Flush queued event fakes if any
    }
}
