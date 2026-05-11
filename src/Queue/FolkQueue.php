<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Folk\Sdk\Rpc\RpcClient;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

/**
 * Laravel Queue driver that pushes jobs via Folk RPC.
 *
 * Jobs are serialized by Laravel and pushed to Folk's job system
 * via the admin RPC socket. Folk decides the storage backend
 * (memory, Redis, etc.) transparently.
 */
final class FolkQueue extends Queue implements QueueContract
{
    public function __construct(
        private readonly RpcClient $rpc,
        private readonly string $defaultQueue = 'default',
    ) {}

    public function size($queue = null): int
    {
        // TODO: implement via jobs.stats RPC
        return 0;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        $payload = $this->createPayload($job, $this->getQueue($queue), $data);

        $this->rpc->call('jobs.push', [
            'queue' => $this->getQueue($queue),
            'payload' => $payload,
        ]);

        return null;
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $this->rpc->call('jobs.push', [
            'queue' => $this->getQueue($queue),
            'payload' => $payload,
        ]);

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        // Delayed jobs not yet supported — push immediately
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null): ?FolkJob
    {
        // Pop is handled by folk-plugin-jobs (Rust side), not by PHP.
        return null;
    }

    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
}
