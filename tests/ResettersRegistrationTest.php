<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\FolkServiceProvider;
use Folk\Sdk\Reset\ResettableInterface;
use Orchestra\Testbench\TestCase;

final class ValidAppResetter implements ResettableInterface
{
    public function reset(): void {}
}

final class NotAResetter
{
}

class ResettersRegistrationTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function getPackageProviders($app): array
    {
        return [FolkServiceProvider::class];
    }

    public function test_built_in_resetters_are_registered(): void
    {
        $resetters = FolkServiceProvider::resetters($this->app);

        $classes = array_map(static fn (ResettableInterface $r): string => $r::class, $resetters);

        $this->assertContains(\Folk\Laravel\Reset\ScopedResetter::class, $classes);
        $this->assertContains(\Folk\Laravel\Reset\InertiaResetter::class, $classes);
        $this->assertContains(\Folk\Laravel\Reset\SessionResetter::class, $classes);
        $this->assertContains(\Folk\Laravel\Reset\AuthResetter::class, $classes);
        $this->assertContains(\Folk\Sdk\Reset\TempUploadResetter::class, $classes);
    }

    public function test_session_resetter_runs_before_auth_resetter(): void
    {
        $classes = array_map(
            static fn (ResettableInterface $r): string => $r::class,
            FolkServiceProvider::resetters($this->app),
        );

        $session = array_search(\Folk\Laravel\Reset\SessionResetter::class, $classes, true);
        $auth = array_search(\Folk\Laravel\Reset\AuthResetter::class, $classes, true);

        $this->assertNotFalse($session);
        $this->assertNotFalse($auth);
        $this->assertLessThan($auth, $session, 'session must be flushed before guards are dropped');
    }

    public function test_app_resetter_from_config_is_registered(): void
    {
        config()->set('folk.resetters', [ValidAppResetter::class]);

        $resetters = FolkServiceProvider::resetters($this->app);
        $classes = array_map(static fn (ResettableInterface $r): string => $r::class, $resetters);

        $this->assertContains(ValidAppResetter::class, $classes);
    }

    public function test_invalid_resetters_are_ignored(): void
    {
        config()->set('folk.resetters', [
            NotAResetter::class,          // does not implement ResettableInterface
            'This\\Class\\Does\\Not\\Exist', // unresolvable
            '',                            // empty
        ]);

        $resetters = FolkServiceProvider::resetters($this->app);
        $classes = array_map(static fn (ResettableInterface $r): string => $r::class, $resetters);

        $this->assertNotContains(NotAResetter::class, $classes);
        // Every returned entry still implements the interface.
        foreach ($resetters as $r) {
            $this->assertInstanceOf(ResettableInterface::class, $r);
        }
    }
}
