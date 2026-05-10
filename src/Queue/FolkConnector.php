<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Contracts\Queue\Queue;

final class FolkConnector implements ConnectorInterface
{
    public function connect(array $config): Queue
    {
        $redis = $config['redis_connection'] ?? 'default';
        $queue = $config['queue'] ?? 'default';

        return new FolkQueue(
            app('redis')->connection($redis),
            $queue,
        );
    }
}
