<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Event dispatched after request has been sent to the HTTP client.
 *
 * This event is fired after the HTTP client returns a response,
 * before any response processing or middleware unwinding occurs.
 *
 * Use Cases:
 * - Record request/response metrics
 * - Log successful API calls
 * - Track API usage statistics
 * - Implement custom caching logic
 *
 * @example
 * ```php
 * $dispatcher->addEventListener(RequestSentEvent::class, function ($event) {
 *     $this->metrics->recordLatency(
 *         $event->getUri(),
 *         $event->getDuration()
 *     );
 *     $this->metrics->recordStatusCode(
 *         $event->getResponse()->getStatusCode()
 *     );
 * });
 * ```
 */
final class RequestSentEvent extends AbstractEvent
{
    /**
     * Create a new request sent event.
     *
     * @param  RequestInterface  $request  The PSR-7 request that was sent
     * @param  ResponseInterface  $response  The PSR-7 response received
     * @param  float  $duration  Duration in milliseconds
     * @param  float  $timestamp  Unix timestamp when request completed (microtime)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly float $duration,
        private readonly float $timestamp
    ) {}

    /**
     * Get the PSR-7 request that was sent.
     *
     * @return RequestInterface The request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the PSR-7 response received.
     *
     * @return ResponseInterface The response
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the request duration in milliseconds.
     *
     * @return float Duration in milliseconds
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the timestamp when the request completed.
     *
     * @return float Unix timestamp with microseconds
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Get the HTTP method.
     *
     * @return string HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Get the request URI.
     *
     * @return string The URI as a string
     */
    public function getUri(): string
    {
        return (string) $this->request->getUri();
    }

    /**
     * Get the HTTP status code.
     *
     * @return int HTTP status code (200, 404, 500, etc.)
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Check if the response was successful (2xx status code).
     *
     * @return bool True if successful, false otherwise
     */
    public function isSuccessful(): bool
    {
        $status = $this->getStatusCode();

        return $status >= 200 && $status < 300;
    }
}
