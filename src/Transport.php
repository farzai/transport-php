<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Contracts\ResponseInterface;
use Farzai\Transport\Middleware\MiddlewareStack;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class Transport implements PsrClientInterface
{
    private readonly MiddlewareStack $middlewareStack;

    /**
     * Create a new transport instance.
     */
    public function __construct(
        private readonly TransportConfig $config
    ) {
        $this->middlewareStack = new MiddlewareStack($this->config->middlewares);
    }

    /**
     * Send a PSR request and get a PSR response (for PSR-18 compatibility).
     */
    public function sendRequest(PsrRequestInterface $request): PsrResponseInterface
    {
        $request = $this->prepareRequest($request);

        $response = $this->middlewareStack->handle(
            request: $request,
            core: fn (PsrRequestInterface $req) => $this->config->client->sendRequest($req)
        );

        return $this->wrapResponse($request, $response);
    }

    /**
     * Send a request and get our custom Response with helpers.
     */
    public function send(PsrRequestInterface $request): ResponseInterface
    {
        $psrResponse = $this->sendRequest($request);

        if ($psrResponse instanceof ResponseInterface) {
            return $psrResponse;
        }

        return new Response($request, $psrResponse);
    }

    /**
     * Create a fluent request builder.
     */
    public function request(): RequestBuilder
    {
        return new RequestBuilder($this);
    }

    /**
     * Convenience method for GET request.
     */
    public function get(string $uri): RequestBuilder
    {
        return $this->request()->method('GET')->uri($uri);
    }

    /**
     * Convenience method for POST request.
     */
    public function post(string $uri): RequestBuilder
    {
        return $this->request()->method('POST')->uri($uri);
    }

    /**
     * Convenience method for PUT request.
     */
    public function put(string $uri): RequestBuilder
    {
        return $this->request()->method('PUT')->uri($uri);
    }

    /**
     * Convenience method for PATCH request.
     */
    public function patch(string $uri): RequestBuilder
    {
        return $this->request()->method('PATCH')->uri($uri);
    }

    /**
     * Convenience method for DELETE request.
     */
    public function delete(string $uri): RequestBuilder
    {
        return $this->request()->method('DELETE')->uri($uri);
    }

    /**
     * Get the underlying PSR-18 client.
     */
    public function getPsrClient(): PsrClientInterface
    {
        return $this->config->client;
    }

    /**
     * Get the logger.
     */
    public function getLogger(): PsrLoggerInterface
    {
        return $this->config->logger;
    }

    /**
     * Get the configuration.
     */
    public function getConfig(): TransportConfig
    {
        return $this->config;
    }

    /**
     * Get the base URI.
     */
    public function getUri(): string
    {
        return $this->config->baseUri;
    }

    /**
     * Get the default headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->config->headers;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->config->timeout;
    }

    /**
     * Get the max retries.
     */
    public function getRetries(): int
    {
        return $this->config->maxRetries;
    }

    /**
     * Prepare the request by adding base URI and default headers.
     */
    private function prepareRequest(PsrRequestInterface $request): PsrRequestInterface
    {
        $uri = $request->getUri();

        // If no host, prepend base URI
        if (empty($uri->getHost()) && ! empty($this->config->baseUri)) {
            $baseUri = new Uri($this->config->baseUri);
            $uri = $baseUri->withPath($baseUri->getPath().$uri->getPath());
            $uri = $uri->withQuery($uri->getQuery());
            $request = $request->withUri($uri);
        }

        // Add default headers
        foreach ($this->config->headers as $name => $value) {
            if (! $request->hasHeader($name)) {
                $request = $request->withHeader($name, $value);
            }
        }

        return $request;
    }

    /**
     * Wrap PSR response in our custom Response.
     */
    private function wrapResponse(PsrRequestInterface $request, PsrResponseInterface $response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return new Response($request, $response);
    }
}
