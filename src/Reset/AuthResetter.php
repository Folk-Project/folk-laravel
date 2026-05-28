<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Illuminate\Contracts\Foundation\Application;

final class AuthResetter implements ResettableInterface
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        $auth = $this->app->make('auth');
        foreach (array_keys(config('auth.guards', [])) as $guard) {
            try {
                $auth->guard($guard)->forgetUser();
            } catch (\Throwable) {}
        }
    }
}
