<?php declare(strict_types=1);
namespace Folk\Laravel\Handler;

use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest as FolkRequest;
use Folk\Sdk\Http\HttpResponse as FolkResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class LaravelHttpHandler implements HttpModeHandler
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function handle(FolkRequest $folkRequest): FolkResponse
    {
        $sfRequest = SymfonyRequest::create(
            uri:     $folkRequest->uri,
            method:  $folkRequest->method,
            server:  $this->buildServerBag($folkRequest->headers),
            content: $folkRequest->body,
        );
        foreach ($folkRequest->headers as $name => $value) {
            $sfRequest->headers->set($name, $value);
        }

        $illuminateRequest = Request::createFromBase($sfRequest);

        /** @var \Illuminate\Contracts\Http\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $illuminateResponse = $kernel->handle($illuminateRequest);
        $kernel->terminate($illuminateRequest, $illuminateResponse);

        return new FolkResponse(
            status:  $illuminateResponse->getStatusCode(),
            headers: $this->extractHeaders($illuminateResponse),
            body:    $illuminateResponse->getContent() ?: '',
        );
    }

    /** @param array<string, string> $headers
     *  @return array<string, string> */
    private function buildServerBag(array $headers): array
    {
        $server = ['SCRIPT_NAME' => 'folk-worker'];
        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = $value;
        }
        return $server;
    }

    /** @return array<string, string> */
    private function extractHeaders(\Symfony\Component\HttpFoundation\Response $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        return $headers;
    }
}
