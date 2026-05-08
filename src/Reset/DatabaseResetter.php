<?php declare(strict_types=1);
namespace Folk\Laravel\Reset;

use Illuminate\Contracts\Foundation\Application;

final class DatabaseResetter
{
    public function __construct(private readonly Application $app) {}

    public function reset(): void
    {
        $db = $this->app->make('db');
        foreach ($db->getConnections() as $connection) {
            try {
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            } catch (\Throwable) {}
        }
    }
}
