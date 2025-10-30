<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Event dispatched when a request fails with an exception.
 *
 * This event is fired when any exception occurs during request processing,
 * including network errors, timeouts, HTTP errors, etc.
 *
 * Use Cases:
 * - Error logging and monitoring
 * - Alert on repeated failures
 * - Track error rates
 * - Implement custom error recovery
 * - Circuit breaker implementation
 *
 * @example
 * ```php
 * $dispatcher->addEventListener(RequestFailedEvent::class, function ($event) {
 *     $this->logger->error('Request failed', [
 *         'method' => $event->getMethod(),
 *         'uri' => $event->getUri(),
 *         'error' => $event->getException()->getMessage(),
 *         'type' => get_class($event->getException()),
 *         'attempt' => $event->getAttemptNumber(),
 *     ]);
 *
 *     // Alert on network errors
 *     if ($event->isNetworkError()) {
 *         $this->alerts->networkIssue($event->getUri());
 *     }
 *
 *     // Track error metrics
 *     $this->metrics->incrementErrorCount($event->getUri());
 * });
 * ```
 */
final class RequestFailedEvent extends AbstractEvent
{
    private float $timestamp;

    /**
     * Create a new request failed event.
     *
     * @param  RequestInterface  $request  The PSR-7 request that failed
     * @param  Throwable  $exception  The exception that was thrown
     * @param  int  $attemptNumber  The attempt number (1-based, useful for retries)
     * @param  float  $timestamp  Unix timestamp when failure occurred (microtime)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Throwable $exception,
        private readonly int $attemptNumber = 1,
        float $timestamp = 0.0
    ) {
        // Use current microtime if not provided
        $this->timestamp = $timestamp === 0.0 ? microtime(true) : $timestamp;
    }

    /**
     * Get the PSR-7 request that failed.
     *
     * @return RequestInterface The request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the exception that caused the failure.
     *
     * @return Throwable The exception
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get the attempt number.
     *
     * For non-retry scenarios, this will be 1.
     * For retry scenarios, this increments with each retry.
     *
     * @return int Attempt number (1-based)
     */
    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Get the timestamp when the failure occurred.
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
     * Get the exception class name.
     *
     * @return string Fully qualified exception class name
     */
    public function getExceptionType(): string
    {
        return get_class($this->exception);
    }

    /**
     * Get the exception message.
     *
     * @return string Exception message
     */
    public function getExceptionMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Check if this is a network error.
     *
     * Network errors include connection failures, DNS failures, etc.
     *
     * @return bool True if network error, false otherwise
     */
    public function isNetworkError(): bool
    {
        $exceptionType = $this->getExceptionType();

        return str_contains($exceptionType, 'NetworkException')
            || str_contains($exceptionType, 'ConnectionException')
            || str_contains($exceptionType, 'ConnectException');
    }

    /**
     * Check if this is a timeout error.
     *
     * @return bool True if timeout error, false otherwise
     */
    public function isTimeoutError(): bool
    {
        $exceptionType = $this->getExceptionType();

        return str_contains($exceptionType, 'TimeoutException');
    }

    /**
     * Check if this is an HTTP error (4xx or 5xx status code).
     *
     * @return bool True if HTTP error, false otherwise
     */
    public function isHttpError(): bool
    {
        $exceptionType = $this->getExceptionType();

        return str_contains($exceptionType, 'HttpException')
            || str_contains($exceptionType, 'ClientException')
            || str_contains($exceptionType, 'ServerException')
            || str_contains($exceptionType, 'BadResponseException');
    }

    /**
     * Check if this is a retry attempt (not the first attempt).
     *
     * @return bool True if this is a retry, false if first attempt
     */
    public function isRetry(): bool
    {
        return $this->attemptNumber > 1;
    }
}
