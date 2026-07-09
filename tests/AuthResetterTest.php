<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\Reset\AuthResetter;
use Illuminate\Auth\GenericUser;
use Orchestra\Testbench\TestCase;

class AuthResetterTest extends TestCase
{
    public function test_forget_guards_drops_cached_guard_and_user(): void
    {
        $auth = $this->app->make('auth');
        $guard = $auth->guard();

        $user = new GenericUser(['id' => 1, 'name' => 'A']);
        $guard->setUser($user);
        // Cached on the warm guard — returned without touching the session.
        $this->assertSame($user, $this->app->make('auth')->guard()->user());

        (new AuthResetter($this->app))->reset();

        // A fresh guard instance is resolved and its user cache is gone.
        $this->assertNotSame($guard, $this->app->make('auth')->guard());
        $this->assertNull($this->app->make('auth')->guard()->user());
    }

    public function test_reset_never_throws(): void
    {
        (new AuthResetter($this->app))->reset();
        $this->assertTrue(true);
    }
}
