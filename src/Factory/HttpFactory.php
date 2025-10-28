<?php

declare(strict_types=1);

namespace Farzai\Transport\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Factory abstraction that auto-detects PSR-17 implementations.
 *
 * This class provides a unified interface for creating PSR-7 objects
 * (Request, Response, Uri, Stream) using PSR-17 factories. It automatically
 * discovers available implementations using php-http/discovery.
 *
 * Design Pattern: Factory Pattern + Singleton Pattern
 * - Encapsulates object creation logic
 * - Auto-detects available PSR-17 implementations
 * - Provides consistent interface regardless of underlying implementation
 *
 * Discovery Order:
 * 1. Nyholm PSR-7 (lightweight, recommended)
 * 2. Guzzle PSR-7 (if installed)
 * 3. Laminas Diactoros
 * 4. Any other PSR-17 compliant implementation
 *
 * @example
 * ```php
 * $factory = HttpFactory::getInstance();
 * $request = $factory->createRequest('GET', 'https://api.example.com');
 * $uri = $factory->createUri('https://example.com');
 * ```
 */
final class HttpFactory
{
    private static ?self $instance = null;

    /**
     * Create a new HTTP factory instance.
     *
     * @param  RequestFactoryInterface|null  $requestFactory  Custom request factory
     * @param  ResponseFactoryInterface|null  $responseFactory  Custom response factory
     * @param  UriFactoryInterface|null  $uriFactory  Custom URI factory
     * @param  StreamFactoryInterface|null  $streamFactory  Custom stream factory
     */
    public function __construct(
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?ResponseFactoryInterface $responseFactory = null,
        private readonly ?UriFactoryInterface $uriFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null
    ) {}

    /**
     * Get singleton instance with auto-detected factories.
     *
     * This method provides a convenient way to access a shared factory instance
     * with automatically discovered PSR-17 implementations.
     *
     * @return self The singleton factory instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing).
     *
     * @internal This method is primarily for testing purposes
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Create a new PSR-7 request.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  UriInterface|string  $uri  Request URI
     * @return RequestInterface The created request
     *
     * @throws \RuntimeException If no request factory is available
     *
     * @example
     * ```php
     * $request = $factory->createRequest('POST', 'https://api.example.com/users');
     * ```
     */
    public function createRequest(string $method, UriInterface|string $uri): RequestInterface
    {
        return $this->getRequestFactory()->createRequest($method, $uri);
    }

    /**
     * Create a new PSR-7 response.
     *
     * @param  int  $code  HTTP status code (default: 200)
     * @param  string  $reasonPhrase  Reason phrase (default: '')
     * @return ResponseInterface The created response
     *
     * @throws \RuntimeException If no response factory is available
     *
     * @example
     * ```php
     * $response = $factory->createResponse(404, 'Not Found');
     * ```
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->getResponseFactory()->createResponse($code, $reasonPhrase);
    }

    /**
     * Create a new PSR-7 URI.
     *
     * @param  string  $uri  URI string
     * @return UriInterface The created URI
     *
     * @throws \RuntimeException If no URI factory is available
     * @throws \InvalidArgumentException If URI is malformed
     *
     * @example
     * ```php
     * $uri = $factory->createUri('https://api.example.com/users?page=1');
     * ```
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return $this->getUriFactory()->createUri($uri);
    }

    /**
     * Create a new PSR-7 stream from a string.
     *
     * @param  string  $content  Stream content
     * @return StreamInterface The created stream
     *
     * @throws \RuntimeException If no stream factory is available
     *
     * @example
     * ```php
     * $stream = $factory->createStream(json_encode(['key' => 'value']));
     * ```
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return $this->getStreamFactory()->createStream($content);
    }

    /**
     * Create a stream from an existing resource.
     *
     * @param  resource  $resource  PHP resource
     * @return StreamInterface The created stream
     *
     * @throws \RuntimeException If no stream factory is available
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->getStreamFactory()->createStreamFromResource($resource);
    }

    /**
     * Create a stream from a file.
     *
     * @param  string  $filename  Path to file
     * @param  string  $mode  File mode (default: 'r')
     * @return StreamInterface The created stream
     *
     * @throws \RuntimeException If file cannot be opened or no stream factory is available
     *
     * @example
     * ```php
     * $stream = $factory->createStreamFromFile('/path/to/file.txt', 'r');
     * ```
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->getStreamFactory()->createStreamFromFile($filename, $mode);
    }

    /**
     * Get the request factory (auto-detected if not provided).
     *
     * @return RequestFactoryInterface The request factory
     *
     * @throws \RuntimeException If no request factory implementation is available
     */
    private function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * Get the response factory (auto-detected if not provided).
     *
     * @return ResponseFactoryInterface The response factory
     *
     * @throws \RuntimeException If no response factory implementation is available
     */
    private function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->responseFactory ?? Psr17FactoryDiscovery::findResponseFactory();
    }

    /**
     * Get the URI factory (auto-detected if not provided).
     *
     * @return UriFactoryInterface The URI factory
     *
     * @throws \RuntimeException If no URI factory implementation is available
     */
    private function getUriFactory(): UriFactoryInterface
    {
        return $this->uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
    }

    /**
     * Get the stream factory (auto-detected if not provided).
     *
     * @return StreamFactoryInterface The stream factory
     *
     * @throws \RuntimeException If no stream factory implementation is available
     */
    private function getStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }
}
