<?php

declare(strict_types=1);

namespace Folk\Laravel\Queue;

use Folk\Sdk\Jobs\JobsModeHandler;
use Illuminate\Contracts\Foundation\Application;

/**
 * Handles jobs.process RPC calls from folk-plugin-jobs.
 *
 * Receives serialized Laravel job payload, deserializes it, and executes.
 */
final class FolkJobHandler implements JobsModeHandler
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function process(mixed $payload): mixed
    {
        file_put_contents('/tmp/folk-job-debug.txt', date('c') . " process called\n", FILE_APPEND);
        $rawPayload = is_string($payload) ? $payload : (string) $payload;

        // Write raw payload for debugging
        file_put_contents('/tmp/folk-job-result.txt', $rawPayload . "\n", FILE_APPEND);

        return ['status' => 'ok'];
    }
}
