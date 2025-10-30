<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Event dispatched after response has been processed through middleware.
 *
 * This is the final successful event in the request lifecycle, fired after
 * all middleware has processed the response.
 *
 * Difference from RequestSentEvent:
 * - RequestSentEvent: Fired immediately after HTTP client returns
 * - ResponseReceivedEvent: Fired after middleware processing
 *
 * Use Cases:
 * - Final response logging
 * - End-to-end metrics (including middleware overhead)
 * - Response caching decisions
 * - Success notifications
 *
 * @example
 * ```php
 * $dispatcher->addEventListener(ResponseReceivedEvent::class, function ($event) {
 *     // Log complete request/response cycle
 *     $this->logger->info('Request completed', [
 *         'method' => $event->getMethod(),
 *         'uri' => $event->getUri(),
 *         'status' => $event->getStatusCode(),
 *         'duration' => $event->getDuration(),
 *         'success' => $event->isSuccessful(),
 *     ]);
 *
 *     // Send notification for slow requests
 *     if ($event->getDuration() > 5000) {  // > 5 seconds
 *         $this->alerts->slowRequest($event);
 *     }
 * });
 * ```
 */
final class ResponseReceivedEvent extends AbstractEvent
{
    /**
     * Create a new response received event.
     *
     * @param  RequestInterface  $request  The PSR-7 request
     * @param  ResponseInterface  $response  The PSR-7 response (possibly modified by middleware)
     * @param  float  $duration  Total duration in milliseconds (including middleware)
     * @param  float  $timestamp  Unix timestamp when processing completed (microtime)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly float $duration,
        private readonly float $timestamp
    ) {}

    /**
     * Get the PSR-7 request.
     *
     * @return RequestInterface The request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the PSR-7 response.
     *
     * This may be different from the original HTTP client response if
     * middleware has modified it.
     *
     * @return ResponseInterface The response (possibly modified)
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the total request duration in milliseconds.
     *
     * Includes time spent in middleware pipeline.
     *
     * @return float Duration in milliseconds
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the timestamp when processing completed.
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

    /**
     * Check if the response was a client error (4xx status code).
     *
     * @return bool True if client error, false otherwise
     */
    public function isClientError(): bool
    {
        $status = $this->getStatusCode();

        return $status >= 400 && $status < 500;
    }

    /**
     * Check if the response was a server error (5xx status code).
     *
     * @return bool True if server error, false otherwise
     */
    public function isServerError(): bool
    {
        $status = $this->getStatusCode();

        return $status >= 500 && $status < 600;
    }
}
