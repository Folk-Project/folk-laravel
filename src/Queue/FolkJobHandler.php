<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Folk\Sdk\Jobs\JobsModeHandler;

/**
 * Handles jobs.process RPC calls from folk-plugin-jobs.
 *
 * Receives serialized Laravel job payload, deserializes it, and executes.
 */
final class FolkJobHandler implements JobsModeHandler
{

    public function process(mixed $payload): mixed
    {
        $rawPayload = is_string($payload) ? $payload : (string) $payload;

        $job = new FolkJob(
            \Illuminate\Container\Container::getInstance(),
            $rawPayload,
            'default',
        );

        $job->fire();

        return ['status' => 'ok'];
    }
}
