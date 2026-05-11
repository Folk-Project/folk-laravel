<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Folk\Sdk\Rpc\RpcClient;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Contracts\Queue\Queue;

final class FolkConnector implements ConnectorInterface
{
    public function connect(array $config): Queue
    {
        $socketPath = $config['rpc_socket'] ?? config('folk.rpc_socket', './tmp/folk.sock');
        $queue = $config['queue'] ?? 'default';

        return new FolkQueue(
            new RpcClient($socketPath),
            $queue,
        );
    }
}
