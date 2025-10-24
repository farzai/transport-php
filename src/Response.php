<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Support\Arr;
use Farzai\Transport\Contracts\ResponseInterface;
use Farzai\Transport\Exceptions\ResponseExceptionFactory;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    protected ?array $jsonDecoded = null;

    protected ?string $content = null;

    protected bool $jsonParsed = false;

    /**
     * Create a new response instance.
     */
    public function __construct(
        protected PsrRequestInterface $request,
        protected PsrResponseInterface $response
    ) {
        //
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
     * @throws \Farzai\Transport\Exceptions\JsonParseException
     */
    public function json(?string $key = null): mixed
    {
        if (! $this->jsonParsed) {
            $body = $this->body();

            if ($body === '') {
                $this->jsonDecoded = null;
                $this->jsonParsed = true;

                return null;
            }

            $this->jsonDecoded = json_decode($body, true);

            $jsonError = json_last_error();
            if ($jsonError !== JSON_ERROR_NONE) {
                throw new \Farzai\Transport\Exceptions\JsonParseException(
                    message: sprintf('Failed to parse JSON: %s', json_last_error_msg()),
                    jsonString: $body,
                    jsonErrorCode: $jsonError,
                    jsonErrorMessage: json_last_error_msg()
                );
            }

            $this->jsonParsed = true;
        }

        if ($this->jsonDecoded === null) {
            return null;
        }

        if (is_null($key)) {
            return $this->jsonDecoded;
        }

        return Arr::get($this->jsonDecoded, $key);
    }

    /**
     * Get the JSON decoded response, returning null instead of throwing on parse error.
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
        return new static($this->request, $this->response->withProtocolVersion($version));
    }

    /**
     * @param  string|array<string>  $value
     */
    public function withHeader(string $name, $value): static
    {
        return new static($this->request, $this->response->withHeader($name, $value));
    }

    /**
     * @param  string|array<string>  $value
     */
    public function withAddedHeader(string $name, $value): static
    {
        return new static($this->request, $this->response->withAddedHeader($name, $value));
    }

    public function withoutHeader(string $name): static
    {
        return new static($this->request, $this->response->withoutHeader($name));
    }

    public function withBody(StreamInterface $body): static
    {
        return new static($this->request, $this->response->withBody($body));
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        return new static($this->request, $this->response->withStatus($code, $reasonPhrase));
    }
}
