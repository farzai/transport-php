<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ResponseBuilder
{
    protected int $statusCode = 200;

    /**
     * @var array<string, array<string>>
     */
    protected array $headers = [];

    /**
     * @var mixed
     */
    protected $body;

    protected string $version = '1.1';

    protected ?string $reason = null;

    protected HttpFactory $httpFactory;

    /**
     * Create a new response builder instance.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory for creating PSR-7 objects
     */
    public function __construct(?HttpFactory $httpFactory = null)
    {
        $this->httpFactory = $httpFactory ?? HttpFactory::getInstance();
    }

    /**
     * Create a new response builder instance.
     */
    public static function create(?HttpFactory $httpFactory = null): ResponseBuilder
    {
        return new static($httpFactory);
    }

    /**
     * Set the response status code.
     */
    public function statusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Set the response headers.
     *
     * @param  array<string, array<string>>  $headers
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->withHeader($name, is_array($value) ? $value : [$value]);
        }

        return $this;
    }

    /**
     * Add a header to the response.
     *
     * @param  mixed  $value
     */
    public function withHeader(string $name, $value): self
    {
        if (! isset($this->headers[$name])) {
            $this->headers[$name] = [];
        }

        $this->headers[$name] = array_merge(
            $this->headers[$name],
            (array) $value
        );

        return $this;
    }

    /**
     * Set the response body.
     *
     * @param  mixed  $body
     */
    public function withBody($body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the response version.
     */
    public function withVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Set the response reason.
     */
    public function withReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Build the response.
     */
    public function build(): PsrResponseInterface
    {
        $response = $this->httpFactory->createResponse(
            $this->statusCode,
            $this->reason ?? ''
        );

        // Add headers
        foreach ($this->headers as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        // Add body if present
        if ($this->body !== null) {
            $stream = is_string($this->body)
                ? $this->httpFactory->createStream($this->body)
                : $this->body;

            $response = $response->withBody($stream);
        }

        // Set protocol version if different from default
        if ($this->version !== '1.1') {
            $response = $response->withProtocolVersion($this->version);
        }

        return $response;
    }
}
