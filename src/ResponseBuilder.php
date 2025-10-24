<?php

declare(strict_types=1);

namespace Farzai\Transport;

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

    /**
     * Create a new response builder instance.
     */
    public static function create(): ResponseBuilder
    {
        return new static;
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
        return new \GuzzleHttp\Psr7\Response(
            $this->statusCode,
            $this->headers,
            $this->body,
            $this->version,
            $this->reason
        );
    }
}
