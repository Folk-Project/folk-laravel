<?php declare(strict_types=1);

// In-process descriptor stub for tests: stands in for the folk extension's
// folk_grpc_descriptors() so the generate command can run without the .so.
// Returns a prebuilt FileDescriptorSet (hello.proto) regardless of the paths,
// exercising the DescriptorProvider in-process branch + ProtoGen end-to-end.
if (!function_exists('folk_grpc_descriptors')) {
    /** @param list<string> $paths */
    function folk_grpc_descriptors(array $paths): string
    {
        return (string) file_get_contents(__DIR__ . '/hello.pb');
    }
}
