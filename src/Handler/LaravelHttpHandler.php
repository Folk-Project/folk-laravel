<?php declare(strict_types=1);
namespace Folk\Laravel\Handler;

use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest as FolkRequest;
use Folk\Sdk\Http\HttpResponse as FolkResponse;
use Folk\Sdk\Http\StreamedBody;
use Folk\Sdk\Http\StreamLimitExceededException;
use Folk\Sdk\Folk;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LaravelHttpHandler implements HttpModeHandler
{
    public function __construct(
        private readonly Application $app,
        /** Default streamed-body size limit in bytes (0 = unlimited). */
        private readonly int $maxRequestBytes = 0,
        /** @var array<string, int> Per-path limits, matched against the URI path. */
        private readonly array $pathLimits = [],
    ) {}

    public function handle(FolkRequest $folkRequest): FolkResponse
    {
        $streamed = null;
        try {
            if ($folkRequest->multipart) {
                $streamed = new StreamedBody($this->limitFor($folkRequest->uri));
                $streamed->drainMultipart();
                $sfRequest = $this->buildRequest($folkRequest, $streamed);
            } elseif ($folkRequest->bodyStream) {
                $streamed = new StreamedBody($this->limitFor($folkRequest->uri));
                $content  = $streamed->readRaw();
                $sfRequest = $this->buildRequest($folkRequest, content: $content);
            } else {
                $sfRequest = $this->buildRequest($folkRequest, content: $folkRequest->body);
            }

            $illuminateRequest = Request::createFromBase($sfRequest);

            /** @var \Illuminate\Contracts\Http\Kernel $kernel */
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            $illuminateResponse = $kernel->handle($illuminateRequest);

            $response = $this->toFolkResponse($illuminateResponse);

            $kernel->terminate($illuminateRequest, $illuminateResponse);

            return $response;
        } catch (StreamLimitExceededException $e) {
            return new FolkResponse(
                status:  413,
                headers: ['Content-Type' => 'application/json'],
                body:    json_encode(['error' => $e->getMessage()]) ?: '{}',
            );
        } finally {
            $streamed?->cleanup();
        }
    }

    /**
     * Build a Symfony request from the Folk request. When `$streamed` is given
     * (multipart) its post fields + spooled files are injected; otherwise the
     * raw `$content` is used as the body.
     */
    private function buildRequest(
        FolkRequest $folkRequest,
        ?StreamedBody $streamed = null,
        ?string $content = null,
    ): SymfonyRequest {
        // Symfony Request::create() seeds the POST bag from $parameters and does
        // NOT parse a urlencoded body itself, so HTML form posts must be parsed
        // here. Multipart fields already arrive parsed in $streamed->post.
        $parameters = $streamed !== null
            ? $streamed->post
            : $this->parseFormBody($folkRequest, $content);
        $files = [];
        if ($streamed !== null) {
            foreach ($streamed->files as $file) {
                if ($file->field === null) {
                    continue;
                }
                $files[$file->field] = new UploadedFile(
                    $file->tmpPath,
                    $file->originalName ?? 'file',
                    $file->contentType,
                    null,
                    true, // test mode: file was not uploaded via SAPI
                );
            }
        }

        $sfRequest = SymfonyRequest::create(
            uri:        $folkRequest->uri,
            method:     $folkRequest->method,
            parameters: $parameters,
            files:      $files,
            server:     $this->buildServerBag($folkRequest->headers),
            content:    $streamed !== null ? null : ($content ?? ''),
        );
        foreach ($folkRequest->headers as $name => $value) {
            $sfRequest->headers->set($name, $value);
        }

        return $sfRequest;
    }

    /**
     * Convert a Laravel/Symfony response to a Folk response. A StreamedResponse
     * is piped chunk-by-chunk through Folk's streaming primitives instead of
     * being buffered.
     */
    private function toFolkResponse(SymfonyResponse $response): FolkResponse
    {
        // StreamedJsonResponse extends StreamedResponse, so this covers both.
        if ($response instanceof StreamedResponse) {
            Folk::writeHead($response->getStatusCode(), $this->extractHeaders($response));
            ob_start(static function (string $chunk): string {
                if ($chunk !== '') {
                    Folk::write($chunk);
                }
                return '';
            }, 65536);
            $response->sendContent();
            ob_end_flush();
            Folk::end();

            return FolkResponse::alreadyStreamed();
        }

        return new FolkResponse(
            status:  $response->getStatusCode(),
            headers: $this->extractHeaders($response),
            body:    $response->getContent() ?: '',
        );
    }

    /**
     * Parse a urlencoded request body into POST parameters. Symfony's
     * Request::create() does not do this; returns [] for non-form bodies.
     *
     * @return array<array-key, mixed>
     */
    private function parseFormBody(FolkRequest $folkRequest, ?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
        }
        $method = strtoupper($folkRequest->method);
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }
        $contentType = '';
        foreach ($folkRequest->headers as $name => $value) {
            if (strcasecmp($name, 'content-type') === 0) {
                $contentType = $value;
                break;
            }
        }
        if (!str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            return [];
        }
        parse_str($content, $parsed);
        return $parsed;
    }

    /** Resolve the streamed-body byte limit for a request path. */
    private function limitFor(string $uri): int
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
        foreach ($this->pathLimits as $pattern => $limit) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($path, rtrim($pattern, '*'))) {
                    return $limit;
                }
            } elseif ($path === $pattern) {
                return $limit;
            }
        }
        return $this->maxRequestBytes;
    }

    /** @param array<string, string> $headers
     *  @return array<string, string> */
    private function buildServerBag(array $headers): array
    {
        $server = ['SCRIPT_NAME' => 'folk-worker'];
        foreach ($headers as $name => $value) {
            $normalized = strtoupper(str_replace('-', '_', $name));
            // Symfony reads CONTENT_TYPE / CONTENT_LENGTH from the server bag
            // (without the HTTP_ prefix) at Request::create() time to decide
            // whether to parse a urlencoded body into the request parameters.
            if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
                $server[$normalized] = $value;
            } else {
                $server['HTTP_' . $normalized] = $value;
            }
        }
        return $server;
    }

    /** @return array<string, string> */
    private function extractHeaders(SymfonyResponse $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        return $headers;
    }
}
