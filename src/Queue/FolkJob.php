<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

final class FolkJob extends Job implements JobContract
{
    private string $rawPayload;

    public function __construct(
        Container $container,
        string $rawPayload,
        string $queue,
    ) {
        $this->container = $container;
        $this->connectionName = 'folk';
        $this->rawPayload = $rawPayload;
        $this->queue = $queue;
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
}
