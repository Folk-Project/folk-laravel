<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Flushes Inertia's shared props between requests.
 *
 * Inertia's `ResponseFactory` is a container singleton that accumulates shared
 * props via `Inertia::share()` (typically from the `HandleInertiaRequests`
 * middleware). In a persistent worker those shared props survive across
 * requests, so anything conditionally/dynamically shared can leak. Octane
 * avoids this by flushing between requests; Folk does the same here.
 *
 * No-op when Inertia is not installed, so the resetter is safe to always
 * register.
 */
final class InertiaResetter implements ResettableInterface
{
    private const FACTORY = 'Inertia\\ResponseFactory';

    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        if (!class_exists(self::FACTORY) || !$this->app->bound(self::FACTORY)) {
            return;
        }
        try {
            $factory = $this->app->make(self::FACTORY);
            if (is_object($factory) && method_exists($factory, 'flushShared')) {
                $factory->flushShared();
            }
        } catch (\Throwable) {
            // Inertia present but not resolvable in this context — ignore.
        }
    }
}
