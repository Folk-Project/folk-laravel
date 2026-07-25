<?php declare(strict_types=1);
namespace Folk\Laravel\Console;

use Folk\Sdk\Grpc\Codegen\ProtoGen;
use Illuminate\Console\Command;

/**
 * `php artisan folk:grpc:generate` — generate readonly DTOs, int-backed enums,
 * and `*Interface` service contracts from `.proto` files, without protoc or
 * ext-protobuf (phase 87). A thin shim over the SDK's {@see ProtoGen} facade;
 * the descriptor set is compiled by the Folk gRPC plugin (in-process when the
 * extension is loaded, otherwise via `folk-server grpc:descriptors`).
 */
final class GenerateGrpcCommand extends Command
{
    protected $signature = 'folk:grpc:generate
        {proto?* : .proto files (default: config folk.grpc.proto)}
        {--out= : Output directory (default: config folk.grpc.generated_dir)}
        {--namespace= : Target PHP namespace (default: config folk.grpc.generated_namespace)}';

    protected $description = 'Generate gRPC DTOs and service interfaces from .proto files (no protoc)';

    public function handle(): int
    {
        $protoArg = $this->argument('proto');
        /** @var list<string> $protos */
        $protos = is_array($protoArg) && $protoArg !== []
            ? array_values(array_map('strval', $protoArg))
            : array_values(array_map('strval', (array) config('folk.grpc.proto', [])));

        if ($protos === []) {
            $this->error('No .proto files given (pass them as arguments or set config folk.grpc.proto).');
            return self::FAILURE;
        }

        $outOpt = $this->option('out');
        $out = is_string($outOpt) && $outOpt !== ''
            ? $outOpt
            : $this->stringConfig('folk.grpc.generated_dir', app_path('Grpc/Generated'));

        $nsOpt = $this->option('namespace');
        $namespace = is_string($nsOpt) && $nsOpt !== ''
            ? $nsOpt
            : $this->stringConfig('folk.grpc.generated_namespace', 'App\\Grpc\\Generated');

        try {
            $written = ProtoGen::run($protos, $out, $namespace);
        } catch (\Throwable $e) {
            $this->error('folk:grpc:generate: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->line('  ' . $path);
        }
        $this->info(count($written) . " file(s) generated in {$out}");
        return self::SUCCESS;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
