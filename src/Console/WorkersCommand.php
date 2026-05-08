<?php declare(strict_types=1);
namespace Folk\Laravel\Console;

use Illuminate\Console\Command;

final class WorkersCommand extends Command
{
    protected $signature = 'folk:workers';
    protected $description = 'Show current Folk worker status';

    public function handle(): int
    {
        // Connect to admin RPC socket, call process.list
        $this->line('Folk worker status: (admin RPC not yet connected in this phase)');
        return self::SUCCESS;
    }
}
