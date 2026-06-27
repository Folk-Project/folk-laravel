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
        // Rust jobs plugin sends {queue: "<conn>.<name>", payload: "..."}
        $rawPayload = is_array($payload) && isset($payload['payload'])
            ? $payload['payload']
            : (is_string($payload) ? $payload : (string) $payload);

        $queue = is_array($payload) && isset($payload['queue'])
            ? (string) $payload['queue']
            : 'default';

        $job = new FolkJob(
            \Illuminate\Container\Container::getInstance(),
            $rawPayload,
            $queue,
        );

        $job->fire();

        return ['status' => 'ok'];
    }
}
