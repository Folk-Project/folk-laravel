<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Flushes the container's scoped instances between requests.
 *
 * Laravel resolves `scoped()` bindings once per request/job "lifecycle" and,
 * under FPM, gets a fresh container each request so they never leak. In a
 * persistent worker (Folk, Octane) the container survives across requests, so
 * scoped instances must be forgotten explicitly — otherwise the first request's
 * instance is reused for the worker's lifetime.
 *
 * This is the Octane-parity fix for state leaks such as Inertia's `SsrState`
 * (a scoped binding that caches the rendered SSR response); without this the
 * first Inertia response is replayed for every later request.
 */
final class ScopedResetter implements ResettableInterface
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        // forgetScopedInstances() lives on the concrete container, not the
        // Application contract; guard for older containers that lack it.
        if (method_exists($this->app, 'forgetScopedInstances')) {
            $this->app->forgetScopedInstances();
        }
    }
}
