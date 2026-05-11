<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

final class FolkJob extends Job implements JobContract
{
    public function __construct(
        Container $container,
        private readonly string $rawPayload,
        private readonly string $queue,
    ) {
        $this->container = $container;
        $this->connectionName = 'folk';
    }

    public function getJobId(): string
    {
        return $this->payload()['uuid'] ?? $this->payload()['id'] ?? '';
    }

    public function getRawBody(): string
    {
        return $this->rawPayload;
    }

    public function attempts(): int
    {
        return ($this->payload()['attempts'] ?? 0) + 1;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }
}
