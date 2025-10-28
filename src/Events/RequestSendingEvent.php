<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

use Psr\Http\Message\RequestInterface;

/**
 * Event dispatched before a request enters the middleware pipeline.
 *
 * This is the first event in the request lifecycle, fired before any
 * middleware processing occurs.
 *
 * Use Cases:
 * - Log all outgoing requests
 * - Add request tracking/correlation IDs
 * - Implement request quotas or rate limiting
 * - Audit API calls
 *
 * @example
 * ```php
 * $dispatcher->addEventListener(RequestSendingEvent::class, function ($event) {
 *     $this->logger->info('Sending request', [
 *         'method' => $event->getRequest()->getMethod(),
 *         'uri' => (string) $event->getRequest()->getUri(),
 *         'time' => $event->getTimestamp(),
 *     ]);
 * });
 * ```
 */
final class RequestSendingEvent extends AbstractEvent
{
    private float $timestamp;

    /**
     * Create a new request sending event.
     *
     * @param  RequestInterface  $request  The PSR-7 request being sent
     * @param  float  $timestamp  Unix timestamp when request started (microtime)
     */
    public function __construct(
        private readonly RequestInterface $request,
        float $timestamp = 0.0
    ) {
        // Use current microtime if not provided
        $this->timestamp = $timestamp === 0.0 ? microtime(true) : $timestamp;
    }

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
     * Get the timestamp when the request started.
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
}
