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
}
