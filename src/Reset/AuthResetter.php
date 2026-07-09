<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Illuminate\Contracts\Foundation\Application;

final class AuthResetter implements ResettableInterface
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        // forgetGuards() drops every resolved guard so the next request rebuilds
        // them from scratch. Per-guard forgetUser() only nulls the cached user and
        // leaves `loggedOut` / `recallAttempted` set on the warm guard, so a logout
        // or a stale flag would leak into the next request (folk-releases #86). This
        // also covers custom guards not listed in `config('auth.guards')`.
        if (!$this->app->bound('auth')) {
            return;
        }
        try {
            $auth = $this->app->make('auth');
            if (is_object($auth) && method_exists($auth, 'forgetGuards')) {
                $auth->forgetGuards();
            }
        } catch (\Throwable) {}
    }
}
