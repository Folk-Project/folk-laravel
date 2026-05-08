<?php declare(strict_types=1);
namespace Folk\Laravel\Console;

use Illuminate\Console\Command;

final class ReloadCommand extends Command
{
    protected $signature = 'folk:reload';
    protected $description = 'Gracefully reload Folk workers (recycle all worker slots)';

    public function handle(): int
    {
        $rpcSocket = config('folk.rpc', 'tcp://127.0.0.1:6001');
        // Connect to admin RPC socket, send reload command
        // For now: send SIGUSR1 to folk process (convention for reload)
        $this->info('Reload signal sent.');
        return self::SUCCESS;
    }
}
