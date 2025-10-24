<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Contracts\ResponseInterface;
use Farzai\Transport\Contracts\SerializerInterface;
use Farzai\Transport\Exceptions\ResponseExceptionFactory;
use Farzai\Transport\Serialization\SerializerFactory;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    protected ?array $jsonDecoded = null;

    protected ?string $content = null;

    protected bool $jsonParsed = false;

    protected SerializerInterface $serializer;

    /**
     * Create a new response instance.
     *
     * @param  PsrRequestInterface  $request  The PSR-7 request
     * @param  PsrResponseInterface  $response  The PSR-7 response
     * @param  SerializerInterface|null  $serializer  The serializer (defaults to JSON)
     */
    public function __construct(
        protected PsrRequestInterface $request,
        protected PsrResponseInterface $response,
        ?SerializerInterface $serializer = null
    ) {
        // Use dependency injection with a sensible default
        $this->serializer = $serializer ?? SerializerFactory::createDefault();
    }

    /**
     * Return the response status code.
     */
    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Return the response body.
     */
    public function body(): string
    {
        if (is_null($this->content)) {
            $this->content = $this->response->getBody()->getContents();
        }

        return $this->content;
    }

    /**
     * Return the response headers.
     *
     * @return array<string, array<string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Check if the response is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode() >= 200 && $this->statusCode() < 300;
    }

    /**
     * Return the json decoded response.
     *
     * Uses the injected serializer (defaults to JsonSerializer with modern error handling).
     *
     * @param  string|null  $key  Optional dot-notation key path (e.g., "user.name")
     * @return mixed The decoded JSON data
     *
     * @throws \Farzai\Transport\Exceptions\JsonParseException
     */
    public function json(?string $key = null): mixed
    {
        // Use caching to avoid re-parsing on subsequent calls
        if (! $this->jsonParsed) {
            $body = $this->body();

            // Delegate to the injected serializer
            $this->jsonDecoded = $this->serializer->decode($body);
            $this->jsonParsed = true;
        }

        // If no key specified, return the full decoded data
        if ($key === null) {
            return $this->jsonDecoded;
        }

        // If decoder already handled key extraction, don't do it again
        // This branch is for backward compatibility when jsonDecoded is already an array
        if (is_array($this->jsonDecoded)) {
            return \Farzai\Support\Arr::get($this->jsonDecoded, $key);
        }

        return null;
    }

    /**
     * Get the JSON decoded response, returning null instead of throwing on parse error.
     *
     * @param  string|null  $key  Optional dot-notation key path
     * @return mixed The decoded data or null on failure
     */
    public function jsonOrNull(?string $key = null): mixed
    {
        try {
            return $this->json($key);
        } catch (\Farzai\Transport\Exceptions\JsonParseException) {
            return null;
        }
    }

    /**
     * Convert response to array.
     *
     * @return array<mixed>
     *
     * @throws \Farzai\Transport\Exceptions\JsonParseException
     */
    public function toArray(): array
    {
        $data = $this->json();

        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Throw an exception if the response is not successful.
     *
     * @param  callable|null  $callback  Custom callback to throw an exception.
     * @return $this
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function throw(?callable $callback = null)
    {
        $callback = $callback ?? function (ResponseInterface $response, ?\Exception $e) {
            if (! $this->isSuccessful()) {
                throw $e;
            }

            return $response;
        };

        return $callback(
            $this,
            ! $this->isSuccessful() ? ResponseExceptionFactory::create($this) : null
        ) ?: $this;
    }

    /**
     * Return the psr request.
     */
    public function getPsrRequest(): PsrRequestInterface
    {
        return $this->request;
    }

    // PSR-7 ResponseInterface implementation

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    /**
     * @return array<string, array<string>>
     */
    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->response->hasHeader($name);
    }

    /**
     * @return array<string>
     */
    public function getHeader(string $name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withProtocolVersion(string $version): static
    {
        return new static($this->request, $this->response->withProtocolVersion($version), $this->serializer);
    }

    /**
     * @param  string|array<string>  $value
     */
    public function withHeader(string $name, $value): static
    {
        return new static($this->request, $this->response->withHeader($name, $value), $this->serializer);
    }

    /**
     * @param  string|array<string>  $value
     */
    public function withAddedHeader(string $name, $value): static
    {
        return new static($this->request, $this->response->withAddedHeader($name, $value), $this->serializer);
    }

    public function withoutHeader(string $name): static
    {
        return new static($this->request, $this->response->withoutHeader($name), $this->serializer);
    }

    public function withBody(StreamInterface $body): static
    {
        return new static($this->request, $this->response->withBody($body), $this->serializer);
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        return new static($this->request, $this->response->withStatus($code, $reasonPhrase), $this->serializer);
    }

    /**
     * Get the serializer instance.
     *
     * @return SerializerInterface The current serializer
     */
    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }
}
