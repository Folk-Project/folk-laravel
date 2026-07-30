<?php

return [
    'rpc' => env('FOLK_RPC', 'tcp://127.0.0.1:6001'),

    'grpc' => [
        // Map gRPC service names to handler classes.
        //
        // With transcoding on (`[grpc] transcode = true` in folk.toml) each
        // handler implements the generated `*Interface` and works with typed
        // DTOs. In passthrough mode (default) handlers accept and return raw
        // protobuf bytes.
        //
        // Example:
        // 'helloworld.Greeter' => App\Grpc\GreeterService::class,
        'services' => [],

        // --- Code generation: server contracts (php artisan folk:grpc:generate) ---
        //
        // Generated DTOs/enums + `*Interface` land under `generated_namespace`.
        // Placeholders (phase 90): `{package}` nests each class by its proto
        // package (io.altessa.type.v1 → Io\Altessa\Type\V1) — cross-package field
        // types reference each other by FQN. List entry-point *service* .proto
        // files; their imports are compiled automatically. Positional CLI args
        // override `proto`.
        'server' => [
            'proto' => [],
            // Defaults (omit to use them):
            //   generated_dir       => app_path('Grpc/Generated/Server')
            //   generated_namespace => 'App\Grpc\Generated\Server\{package}'
            'generated_dir' => null,
            'generated_namespace' => null,
        ],

        // --- Code generation: gRPC clients (call upstream services, phase 88) ---
        //
        // Each entry names a `[grpc.clients.<name>]` upstream in folk.toml
        // (address/tls/deadline/retries live there — the Rust plugin owns the
        // transport). Point at the upstream's .proto so generation can emit a typed
        // `{Service}Client` stub. Placeholders: `{client_name}` = this key,
        // `{package}` as above.
        //
        //   $catalog = \Folk\Sdk\Folk::grpcClient(\App\Grpc\Generated\Client\catalog\...\CatalogClient::class);
        //   $resp = $catalog->Search(new SearchRequest(query: 'phone'));
        //
        // Example:
        // 'catalog' => [
        //     'proto' => ['proto/clients/catalog.proto'],
        //     // Defaults (omit to use them):
        //     //   generated_dir       => app_path('Grpc/Generated/Client/{client_name}')
        //     //   generated_namespace => 'App\Grpc\Generated\Client\{client_name}\{package}'
        //     'generated_dir' => null,
        //     'generated_namespace' => null,
        // ],
        'clients' => [],
    ],

    // Request body streaming.
    //
    // Streaming itself is enabled in folk.toml via `[http] stream_request_body`
    // + `stream_request_body_paths`. These options only cap the size of a
    // streamed body on the PHP side, since `max_request_size` is not enforced
    // for streamed paths.
    'streaming' => [
        // Default limit (bytes) for any streamed request body. 0 = unlimited.
        'max_request_bytes' => (int) env('FOLK_STREAM_MAX_BYTES', 0),

        // Optional per-path limits, matched against the request path. A pattern
        // ending in `*` is a prefix match; otherwise it matches exactly.
        // Example:
        // '/api/files/*' => 1073741824, // 1 GiB
        'limits' => [],
    ],

    // Application-registered per-request resetters.
    //
    // Folk keeps the application booted across requests (Octane-style), so any
    // singleton/scoped state you mutate during a request must be reset before
    // the next one. Folk already resets auth, database transactions, events,
    // queue, temp uploads, the container's scoped instances, and Inertia's
    // shared props. Register additional resetters here to clear your own
    // package/app state. Each class must implement
    // \Folk\Sdk\Reset\ResettableInterface and is resolved from the container.
    //
    // Example:
    // 'resetters' => [
    //     App\Folk\MyStateResetter::class,
    // ],
    'resetters' => [],
];
