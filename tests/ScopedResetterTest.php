<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\Reset\ScopedResetter;
use Orchestra\Testbench\TestCase;

class ScopedResetterTest extends TestCase
{
    public function test_forgets_scoped_instances(): void
    {
        $this->app->scoped('folk-scoped-test', fn () => new \stdClass());

        $first = $this->app->make('folk-scoped-test');
        // Same instance while the "request" is alive.
        $this->assertSame($first, $this->app->make('folk-scoped-test'));

        (new ScopedResetter($this->app))->reset();

        // After reset a fresh instance is resolved — no state carried over.
        $this->assertNotSame($first, $this->app->make('folk-scoped-test'));
    }

    public function test_reset_never_throws(): void
    {
        (new ScopedResetter($this->app))->reset();
        $this->assertTrue(true);
    }
}
