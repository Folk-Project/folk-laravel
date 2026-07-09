<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Flushes the cached session store(s) between requests.
 *
 * `StartSession` middleware is a container singleton holding the `session`
 * SessionManager singleton, which caches the resolved `Store` for the worker's
 * lifetime. `Store::start()` does `array_replace($this->attributes, readFromHandler())`
 * and `save()` never clears `$attributes`, so keys absent from the *incoming*
 * request's stored data (e.g. the `login_*` auth key on a cookie-less request)
 * survive from the previous request — the auth guard then reads the previous
 * user's session (folk-releases #86).
 *
 * We flush the store IN PLACE via `SessionManager::getDrivers()` rather than
 * `forgetInstance('session')`: forgetting the container binding would replace the
 * object while the warm middleware keeps its original reference, desyncing the
 * store between `StartSession` and the guard (and breaking the `laravel-session`
 * cookie). `flush()` clears only the attributes and keeps object identity, so
 * everyone keeps sharing one clean store. Octane-parity.
 */
final class SessionResetter implements ResettableInterface
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        if (!$this->app->bound('session')) {
            return;
        }
        try {
            $manager = $this->app->make('session');
            // getDrivers() returns only the already-resolved stores, so this is a
            // no-op when the request never touched the session.
            if (!is_object($manager) || !method_exists($manager, 'getDrivers')) {
                return;
            }
            foreach ($manager->getDrivers() as $store) {
                if (is_object($store) && method_exists($store, 'flush')) {
                    $store->flush();
                }
            }
        } catch (\Throwable) {}
    }
}
