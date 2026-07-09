<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\Reset\SessionResetter;
use Orchestra\Testbench\TestCase;

class SessionResetterTest extends TestCase
{
    public function test_flush_clears_leaked_session_attributes(): void
    {
        // Resolve + cache the store, mimicking a previous request that stored an
        // auth key (login_<guard>_<hash>).
        $store = $this->app->make('session')->driver();
        $store->put('login_web_abc', 42);
        $this->assertSame(42, $store->get('login_web_abc'));

        (new SessionResetter($this->app))->reset();

        // Attributes cleared so the next request loads only from its own cookie.
        $this->assertNull($store->get('login_web_abc'));
        $this->assertSame([], $store->all());

        // Object identity preserved — no forgetInstance(), so the warm middleware
        // and the guard keep sharing this one store (cookie keeps emitting).
        $this->assertSame($store, $this->app->make('session')->driver());
    }

    public function test_reset_never_throws_without_resolved_session(): void
    {
        (new SessionResetter($this->app))->reset();
        $this->assertTrue(true);
    }
}
