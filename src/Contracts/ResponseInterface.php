<?php

declare(strict_types=1);

namespace Farzai\Transport\Contracts;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

interface ResponseInterface extends PsrResponseInterface
{
    /**
     * Return the response status code.
     */
    public function statusCode(): int;

    /**
     * Return the response body.
     */
    public function body(): string;

    /**
     * Return the response headers.
     *
     * @return array<string, array<string>>
     */
    public function headers(): array;

    /**
     * Check if the response is successful.
     */
    public function isSuccessful(): bool;

    /**
     * Return the json decoded response.
     *
     * @throws \Farzai\Transport\Exceptions\JsonParseException
     */
    public function json(?string $key = null): mixed;

    /**
     * Get the JSON decoded response, returning null instead of throwing on parse error.
     */
    public function jsonOrNull(?string $key = null): mixed;

    /**
     * Convert response to array.
     *
     * @return array<mixed>
     *
     * @throws \Farzai\Transport\Exceptions\JsonParseException
     */
    public function toArray(): array;

    /**
     * Throw an exception if the response is not successful.
     *
     * @param  callable|null  $callback  Custom callback to throw an exception.
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function throw(?callable $callback = null): static;

    /**
     * Return the psr request.
     */
    public function getPsrRequest(): PsrRequestInterface;
}
