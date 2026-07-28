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
        foreach (glob($this->outDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->outDir);
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
