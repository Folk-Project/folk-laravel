<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Contracts\Queue\Queue;

final class FolkConnector implements ConnectorInterface
{
    /** @param array<string, mixed> $config */
    public function connect(array $config): Queue
    {
        $queue = $config['queue'] ?? 'default';
        return new FolkQueue($queue);
    }
}
