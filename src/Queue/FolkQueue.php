<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

/**
 * Laravel Queue driver that pushes jobs via Folk extension.
 *
 * In extension mode, jobs are pushed directly to the Rust jobs plugin
 * via folk_call() (in-process, zero IPC). Falls back to RPC socket
 * if the extension is not loaded.
 */
final class FolkQueue extends Queue implements QueueContract
{
    public function __construct(
        private readonly string $defaultQueue = 'default',
    ) {}

    public function size($queue = null): int
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        $payload = $this->createPayload($job, $this->getQueue($queue), $data);
        $this->pushToFolk($this->getQueue($queue), $payload);
        return null;
    }

    /** @param array<string, mixed> $options */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $this->pushToFolk($this->getQueue($queue), $payload);
        return null;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null): ?FolkJob
    {
        return null;
    }

    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }

    private function pushToFolk(string $queue, string $payload): void
    {
        \folk_call('jobs.push', \msgpack_pack([
            'queue' => $queue,
            'payload' => $payload,
        ]));
    }
}
