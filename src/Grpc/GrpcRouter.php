<?php

declare(strict_types=1);

namespace Folk\Laravel\Grpc;

use Folk\Sdk\Grpc\GrpcModeHandler;

final class GrpcRouter implements GrpcModeHandler
{
    /** @var array<string, object> service name → handler instance */
    private array $services = [];

    public function register(string $serviceName, object $handler): void
    {
        $this->services[$serviceName] = $handler;
    }

    public function call(string $service, string $method, string $payload): string
    {
        $handler = $this->services[$service]
            ?? throw new \RuntimeException("Unknown gRPC service: {$service}");

        if (!method_exists($handler, $method)) {
            throw new \RuntimeException("Unknown method: {$service}/{$method}");
        }

        return $handler->$method($payload);
    }
}
