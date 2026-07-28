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

        // --- Code generation (php artisan folk:grpc:generate) ---
        //
        // .proto files to generate DTOs/interfaces from. Also accepted as CLI
        // arguments, which override this list.
        'proto' => [],

        // Where generated code is written, and its PHP namespace. null =
        // app_path('Grpc/Generated') with the App\Grpc\Generated namespace.
        'generated_dir' => null,
        'generated_namespace' => 'App\\Grpc\\Generated',

        // --- gRPC clients: call upstream services (phase 88) ---
        //
        // Each entry names a `[grpc.clients.<name>]` upstream configured in
        // folk.toml (address/tls/deadline/retries live there — the Rust plugin
        // owns the transport). Here you point at the upstream's .proto contract so
        // `php artisan folk:grpc:generate` can emit a typed `{Service}Client` stub.
        //
        // Call it with the generated stub:
        //   $catalog = \Folk\Sdk\Folk::grpcClient(\App\Grpc\Clients\Catalog\CatalogClient::class);
        //   $resp = $catalog->Search(new SearchRequest(query: 'phone'));
        //
        // Example:
        // 'catalog' => [
        //     'proto' => ['proto/clients/catalog.proto'],
        //     // null = app_path('Grpc/Clients/Catalog'), App\Grpc\Clients\Catalog
        //     'generated_dir' => null,
        //     'generated_namespace' => 'App\\Grpc\\Clients\\Catalog',
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
