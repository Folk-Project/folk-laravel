<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\FolkServiceProvider;
use Orchestra\Testbench\TestCase;

require_once __DIR__ . '/Fixtures/grpc_stub.php';

final class GenerateGrpcCommandTest extends TestCase
{
    private string $outDir;

    /** @param \Illuminate\Foundation\Application $app */
    protected function getPackageProviders($app): array
    {
        return [FolkServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->outDir = sys_get_temp_dir() . '/folk-laravel-grpc-' . uniqid();
    }

    protected function tearDown(): void
    {
        // Recursive cleanup — phase-90 output can nest by package.
        $rrm = static function (string $dir) use (&$rrm): void {
            foreach (glob($dir . '/*') ?: [] as $path) {
                is_dir($path) ? $rrm($path) : @unlink($path);
            }
            @rmdir($dir);
        };
        $rrm($this->outDir);
        parent::tearDown();
    }

    public function test_generates_dtos_and_interface(): void
    {
        $this->artisan('folk:grpc:generate', [
            'proto' => ['proto/hello.proto'],
            '--out' => $this->outDir,
            '--namespace' => 'App\\Grpc\\Generated',
        ])->assertSuccessful();

        $this->assertFileExists($this->outDir . '/HelloRequest.php');
        $this->assertFileExists($this->outDir . '/HelloReply.php');
        $this->assertFileExists($this->outDir . '/GreeterInterface.php');

        $source = (string) file_get_contents($this->outDir . '/HelloRequest.php');
        $this->assertStringContainsString('namespace App\\Grpc\\Generated;', $source);
        $this->assertStringContainsString('public const FOLK_FIELDS', $source);
    }

    public function test_fails_without_proto_files(): void
    {
        $this->artisan('folk:grpc:generate')->assertFailed();
    }

    public function test_generates_server_from_config_block_with_package_layout(): void
    {
        // Phase 90: config-driven server generation with a {package} namespace →
        // classes nest by proto package (folk.test.hello → Folk\Test\Hello).
        config()->set('folk.grpc.server', [
            'proto' => ['proto/hello.proto'],
            'generated_dir' => $this->outDir,
            'generated_namespace' => 'App\\Grpc\\Generated\\Server\\{package}',
        ]);

        $this->artisan('folk:grpc:generate')->assertSuccessful();

        $nested = $this->outDir . '/Folk/Test/Hello/HelloRequest.php';
        $this->assertFileExists($nested);
        $src = (string) file_get_contents($nested);
        $this->assertStringContainsString('namespace App\\Grpc\\Generated\\Server\\Folk\\Test\\Hello;', $src);
    }

    public function test_legacy_flat_config_still_generates(): void
    {
        // Phase 90 BC: the old flat folk.grpc.proto/generated_* keys still work.
        config()->set('folk.grpc.proto', ['proto/hello.proto']);
        config()->set('folk.grpc.generated_dir', $this->outDir);
        config()->set('folk.grpc.generated_namespace', 'App\\Legacy\\Flat');

        $this->artisan('folk:grpc:generate')->assertSuccessful();

        $this->assertFileExists($this->outDir . '/HelloRequest.php', 'flat layout preserved');
        $src = (string) file_get_contents($this->outDir . '/HelloRequest.php');
        $this->assertStringContainsString('namespace App\\Legacy\\Flat;', $src);
    }

    public function test_generates_client_stub_from_config(): void
    {
        config()->set('folk.grpc.clients', [
            'greeter' => [
                'proto' => ['proto/hello.proto'],
                'generated_dir' => $this->outDir,
                'generated_namespace' => 'App\\Grpc\\Clients\\Greeter',
            ],
        ]);

        $this->artisan('folk:grpc:generate', ['--client' => 'greeter'])->assertSuccessful();

        $this->assertFileExists($this->outDir . '/GreeterClient.php');
        // DTOs are emitted for the client role too.
        $this->assertFileExists($this->outDir . '/HelloRequest.php');

        $src = (string) file_get_contents($this->outDir . '/GreeterClient.php');
        $this->assertStringContainsString('namespace App\\Grpc\\Clients\\Greeter;', $src);
        $this->assertStringContainsString('extends GrpcClient', $src);
        $this->assertStringContainsString("public const CLIENT = 'greeter';", $src);
        $this->assertStringContainsString('public function SayHello(', $src);
    }

    public function test_unknown_client_fails(): void
    {
        config()->set('folk.grpc.clients', []);
        $this->artisan('folk:grpc:generate', ['--client' => 'nope'])->assertFailed();
    }
}
