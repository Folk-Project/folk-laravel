<?php

declare(strict_types=1);

namespace Folk\Laravel\Grpc;

use Folk\Sdk\Grpc\GrpcModeHandler;

final class GrpcRouter implements GrpcModeHandler
{
    /** @var array<string, object> service name → handler instance */
    private array $services = [];

    /**
     * Register a gRPC service handler.
     *
     * Supports two formats:
     * - String key: register('greeter.Greeter', $handler)
     * - Interface with NAME constant: register(GreeterInterface::class, $handler)
     */
    public function register(string $serviceNameOrInterface, object $handler): void
    {
        $serviceName = $this->resolveServiceName($serviceNameOrInterface, $handler);
        $this->services[$serviceName] = $handler;
    }

    public function call(string $service, string $method, string $payload, \Folk\Sdk\Grpc\Context $context): string
    {
        $handler = $this->services[$service]
            ?? throw new \RuntimeException("Unknown gRPC service: {$service}");

        if (!method_exists($handler, $method)) {
            throw new \RuntimeException("Unknown method: {$service}/{$method}");
        }

        // Check if method expects typed protobuf messages
        $ref = new \ReflectionMethod($handler, $method);
        $params = $ref->getParameters();
        $lastParam = $params[count($params) - 1] ?? null;

        // If last parameter is a protobuf message, use typed dispatch
        if ($lastParam !== null && $this->isProtobufParam($lastParam)) {
            return $this->callTyped($handler, $method, $payload, $params, $context);
        }

        // Raw bytes mode: method(string $payload): string
        return $handler->$method($payload);
    }

    /** @param list<\ReflectionParameter> $params */
    private function callTyped(object $handler, string $method, string $payload, array $params, \Folk\Sdk\Grpc\Context $context): string
    {
        $args = [];

        foreach ($params as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName !== null && is_subclass_of($typeName, \Google\Protobuf\Internal\Message::class)) {
                /** @var \Google\Protobuf\Internal\Message $message */
                $message = new $typeName();
                $message->mergeFromString($payload);
                $args[] = $message;
            } elseif ($typeName === \Folk\Sdk\Grpc\Context::class) {
                $args[] = $context;
            } else {
                // RoadRunner ContextInterface or similar — pass our Context
                $args[] = $context;
            }
        }

        /** @var \Google\Protobuf\Internal\Message $result */
        $result = $handler->$method(...$args);

        return $result->serializeToString();
    }

    private function resolveServiceName(string $nameOrInterface, object $handler): string
    {
        // If it's an interface/class with NAME constant, use that
        if (defined("{$nameOrInterface}::NAME")) {
            return constant("{$nameOrInterface}::NAME");
        }

        // If the handler implements an interface with NAME constant
        foreach (class_implements($handler) as $interface) {
            if (defined("{$interface}::NAME")) {
                return constant("{$interface}::NAME");
            }
        }

        // Otherwise treat as literal service name
        return $nameOrInterface;
    }

    private function isProtobufParam(\ReflectionParameter $param): bool
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        return is_subclass_of($type->getName(), \Google\Protobuf\Internal\Message::class);
    }
}
