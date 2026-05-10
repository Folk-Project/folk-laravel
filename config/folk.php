<?php

return [
    'rpc' => env('FOLK_RPC', 'tcp://127.0.0.1:6001'),

    'grpc' => [
        // Map gRPC service names to handler classes.
        // Each handler class must have methods matching the gRPC method names,
        // accepting raw protobuf bytes and returning raw protobuf bytes.
        //
        // Example:
        // 'helloworld.Greeter' => App\Grpc\GreeterService::class,
        'services' => [],
    ],
];
