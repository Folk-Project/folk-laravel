<?php declare(strict_types=1);

namespace Inertia {
    // Minimal stand-in for Inertia's ResponseFactory so InertiaResetter can be
    // exercised without pulling the real package. Records flushShared() calls.
    if (!class_exists(ResponseFactory::class, false)) {
        class ResponseFactory
        {
            public int $flushed = 0;

            public function flushShared(): void
            {
                $this->flushed++;
            }
        }
    }
}

namespace Folk\Laravel\Tests {

    use Folk\Laravel\Reset\InertiaResetter;
    use Orchestra\Testbench\TestCase;

    class InertiaResetterTest extends TestCase
    {
        public function test_noop_when_inertia_not_bound(): void
        {
            // Nothing bound → resetter must be a safe no-op, not throw.
            (new InertiaResetter($this->app))->reset();
            $this->assertFalse($this->app->bound(\Inertia\ResponseFactory::class));
        }

        public function test_flushes_shared_props_when_bound(): void
        {
            $factory = new \Inertia\ResponseFactory();
            $this->app->instance(\Inertia\ResponseFactory::class, $factory);

            (new InertiaResetter($this->app))->reset();

            $this->assertSame(1, $factory->flushed);
        }
    }
}
