<?php declare(strict_types=1);

namespace Folk\Laravel\Tests;

use Folk\Laravel\Handler\LaravelHttpHandler;
use Folk\Sdk\Http\HttpRequest;
use Orchestra\Testbench\TestCase;

class LaravelHttpHandlerTest extends TestCase
{
    public function test_handles_get_request(): void
    {
        $this->app->make('router')->get('/folk-test', fn() => response('ok', 200));

        $handler = new LaravelHttpHandler($this->app);
        $request = new HttpRequest('GET', '/folk-test', [], '');
        $response = $handler->handle($request);

        $this->assertSame(200, $response->status);
        $this->assertSame('ok', $response->body);
    }

    public function test_handles_post_request(): void
    {
        $this->app->make('router')->post('/folk-post', fn() => response('created', 201));

        $handler = new LaravelHttpHandler($this->app);
        $request = new HttpRequest('POST', '/folk-post', ['content-type' => 'application/json'], '{"key":"value"}');
        $response = $handler->handle($request);

        $this->assertSame(201, $response->status);
        $this->assertSame('created', $response->body);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        $handler = new LaravelHttpHandler($this->app);
        $request = new HttpRequest('GET', '/nonexistent', [], '');
        $response = $handler->handle($request);

        $this->assertSame(404, $response->status);
    }

    public function test_response_includes_headers(): void
    {
        $this->app->make('router')->get('/folk-headers', fn() => response('ok', 200)->header('X-Folk', 'test'));

        $handler = new LaravelHttpHandler($this->app);
        $request = new HttpRequest('GET', '/folk-headers', [], '');
        $response = $handler->handle($request);

        $this->assertSame(200, $response->status);
        $this->assertArrayHasKey('x-folk', $response->headers);
        $this->assertSame('test', $response->headers['x-folk']);
    }
}
