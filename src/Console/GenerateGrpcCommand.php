<?php declare(strict_types=1);
namespace Folk\Laravel\Console;

use Folk\Sdk\Grpc\Codegen\ProtoGen;
use Folk\Sdk\Grpc\Codegen\ProtoGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * `php artisan folk:grpc:generate` — generate readonly DTOs, int-backed enums,
 * and either server `*Interface` contracts or client `*Client` stubs from `.proto`
 * files, without protoc or ext-protobuf (phase 87 server, phase 88 client). A thin
 * shim over the SDK's {@see ProtoGen} facade; the descriptor set is compiled by the
 * Folk gRPC plugin (in-process when the extension is loaded, otherwise via
 * `folk-server grpc:descriptors`).
 *
 * With no arguments it generates the server contracts from `folk.grpc.proto` and a
 * client stub for every configured `folk.grpc.clients.<name>`. Positional `.proto`
 * arguments generate server contracts only; `--client[=name]` generates client
 * stubs only.
 */
final class GenerateGrpcCommand extends Command
{
    protected $signature = 'folk:grpc:generate
        {proto?* : .proto files for the server role (default: config folk.grpc.proto)}
        {--out= : Output directory for the server role (default: config folk.grpc.generated_dir)}
        {--namespace= : Target PHP namespace for the server role (default: config folk.grpc.generated_namespace)}
        {--client= : Generate client stubs for folk.grpc.clients.<name> (all configured clients if no name)}';

    protected $description = 'Generate gRPC DTOs, server interfaces, and client stubs from .proto files (no protoc)';

    public function handle(): int
    {
        // `--client=name` → that client's stubs only.
        $nameOpt = $this->option('client');
        if (is_string($nameOpt) && $nameOpt !== '') {
            return $this->generateClients($nameOpt, true);
        }

        $protoArg = $this->argument('proto');
        $explicit = is_array($protoArg) && $protoArg !== [];
        $serverProtos = $explicit
            ? array_values(array_map('strval', $protoArg))
            : array_values(array_map('strval', (array) config('folk.grpc.proto', [])));
        $clients = $this->clientConfigs();

        if ($serverProtos === [] && $clients === []) {
            $this->error('Nothing to generate: pass .proto files, or set config folk.grpc.proto / folk.grpc.clients.');
            return self::FAILURE;
        }

        $serverOk = $serverProtos === []
            || $this->generateServer($serverProtos) === self::SUCCESS;
        // Positional args mean "server only"; config-driven runs also do clients.
        $clientOk = $explicit || $clients === []
            || $this->generateClients(null, false) === self::SUCCESS;

        return $serverOk && $clientOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param list<string> $protos
     */
    private function generateServer(array $protos): int
    {
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
            $this->error('folk:grpc:generate (server): ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->line('  ' . $path);
        }
        $this->info(count($written) . " server file(s) generated in {$out}");
        return self::SUCCESS;
    }

    /**
     * Generate `{Service}Client` stubs for the configured clients. When `$only` is
     * given, generates just that client (erroring if it is not configured).
     */
    private function generateClients(?string $only, bool $requireAny): int
    {
        $clients = $this->clientConfigs();

        if ($only !== null) {
            if (!isset($clients[$only])) {
                $this->error("folk:grpc:generate: no client '{$only}' in config folk.grpc.clients.");
                return self::FAILURE;
            }
            $clients = [$only => $clients[$only]];
        }

        if ($clients === []) {
            if ($requireAny) {
                $this->error('folk:grpc:generate: no clients configured in folk.grpc.clients.');
                return self::FAILURE;
            }
            return self::SUCCESS;
        }

        $ok = true;
        foreach ($clients as $name => $cfg) {
            if ($cfg['proto'] === []) {
                $this->error("folk:grpc:generate: client '{$name}' has no 'proto' configured.");
                $ok = false;
                continue;
            }
            try {
                $written = ProtoGen::run(
                    $cfg['proto'],
                    $cfg['dir'],
                    $cfg['namespace'],
                    null,
                    ProtoGenerator::ROLE_CLIENT,
                    $name,
                );
            } catch (\Throwable $e) {
                $this->error("folk:grpc:generate (client {$name}): " . $e->getMessage());
                $ok = false;
                continue;
            }

            foreach ($written as $path) {
                $this->line('  ' . $path);
            }
            $this->info(count($written) . " client file(s) for '{$name}' generated in {$cfg['dir']}");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Normalize `folk.grpc.clients` into resolved per-client generation targets.
     *
     * @return array<string, array{proto: list<string>, dir: string, namespace: string}>
     */
    private function clientConfigs(): array
    {
        $raw = config('folk.grpc.clients', []);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $name => $cfg) {
            $name = (string) $name;
            $cfg = is_array($cfg) ? $cfg : [];

            $proto = array_values(array_map('strval', (array) ($cfg['proto'] ?? [])));

            $dirRaw = $cfg['generated_dir'] ?? null;
            $dir = is_string($dirRaw) && $dirRaw !== ''
                ? $dirRaw
                : app_path('Grpc/Clients/' . Str::studly($name));

            $nsRaw = $cfg['generated_namespace'] ?? null;
            $namespace = is_string($nsRaw) && $nsRaw !== ''
                ? $nsRaw
                : 'App\\Grpc\\Clients\\' . Str::studly($name);

            $out[$name] = ['proto' => $proto, 'dir' => $dir, 'namespace' => $namespace];
        }

        return $out;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
