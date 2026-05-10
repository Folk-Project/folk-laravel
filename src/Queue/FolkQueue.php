<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Redis\Connections\Connection;

/**
 * Laravel Queue driver that pushes jobs to Redis lists consumed by folk-plugin-jobs.
 *
 * Jobs are serialized as JSON and RPUSH'd to a Redis key matching the queue name.
 * folk-plugin-jobs uses BLPOP on the same key to consume jobs.
 */
final class FolkQueue extends Queue implements QueueContract
{
    public function __construct(
        private readonly Connection $redis,
        private readonly string $defaultQueue = 'default',
    ) {}

    public function size($queue = null): int
    {
        return (int) $this->redis->llen($this->getQueue($queue));
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        $payload = $this->createPayload($job, $this->getQueue($queue), $data);

        return $this->redis->rpush($this->getQueue($queue), $payload);
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        return $this->redis->rpush($this->getQueue($queue), $payload);
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        // Delayed jobs: use Redis sorted set with score = timestamp
        $payload = $this->createPayload($job, $this->getQueue($queue), $data);
        $delay = $this->secondsUntil($delay);

        return $this->redis->zadd(
            $this->getQueue($queue) . ':delayed',
            $this->currentTime() + $delay,
            $payload,
        );
    }

    public function pop($queue = null): ?FolkJob
    {
        // Pop is handled by folk-plugin-jobs (Rust side), not by PHP.
        // This method exists for interface compliance but is not normally called.
        $payload = $this->redis->lpop($this->getQueue($queue));

        if ($payload === null) {
            return null;
        }

        return new FolkJob(
            $this->container,
            $this,
            (string) $payload,
            $this->getQueue($queue),
        );
    }

    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
}
