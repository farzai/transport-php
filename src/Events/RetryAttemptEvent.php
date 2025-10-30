<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Event dispatched before each retry attempt.
 *
 * This event is fired just before the retry middleware attempts to resend
 * a failed request. It provides information about the failure and retry strategy.
 *
 * Use Cases:
 * - Log retry attempts
 * - Monitor retry behavior
 * - Implement custom retry logic
 * - Alert on excessive retries
 * - Track retry success rates
 *
 * @example
 * ```php
 * $dispatcher->addEventListener(RetryAttemptEvent::class, function ($event) {
 *     $this->logger->warning('Retrying request', [
 *         'uri' => $event->getUri(),
 *         'attempt' => $event->getAttemptNumber(),
 *         'max_attempts' => $event->getMaxAttempts(),
 *         'delay' => $event->getDelay(),
 *         'reason' => $event->getReason()->getMessage(),
 *     ]);
 *
 *     // Alert if approaching max retries
 *     if ($event->getAttemptNumber() >= $event->getMaxAttempts() - 1) {
 *         $this->alerts->retryExhaustion($event->getUri());
 *     }
 * });
 * ```
 */
final class RetryAttemptEvent extends AbstractEvent
{
    private float $timestamp;

    /**
     * Create a new retry attempt event.
     *
     * @param  RequestInterface  $request  The PSR-7 request being retried
     * @param  Throwable  $reason  The exception that triggered the retry
     * @param  int  $attemptNumber  The attempt number (1-based, so 2 = first retry)
     * @param  int  $maxAttempts  Maximum number of attempts configured
     * @param  int  $delay  Delay before retry in milliseconds
     * @param  float  $timestamp  Unix timestamp when retry will occur (microtime)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Throwable $reason,
        private readonly int $attemptNumber,
        private readonly int $maxAttempts,
        private readonly int $delay,
        float $timestamp = 0.0
    ) {
        // Use current microtime if not provided
        $this->timestamp = $timestamp === 0.0 ? microtime(true) : $timestamp;
    }

    /**
     * Get the PSR-7 request being retried.
     *
     * @return RequestInterface The request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the exception that triggered the retry.
     *
     * @return Throwable The exception
     */
    public function getReason(): Throwable
    {
        return $this->reason;
    }

    /**
     * Get the current attempt number.
     *
     * Note: This is 1-based, so:
     * - 1 = initial attempt (not a retry)
     * - 2 = first retry
     * - 3 = second retry
     *
     * @return int Attempt number (1-based)
     */
    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Get the maximum number of attempts configured.
     *
     * @return int Maximum attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the delay before retry in milliseconds.
     *
     * @return int Delay in milliseconds
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Get the timestamp when the retry will occur.
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
     * Get the number of remaining attempts.
     *
     * @return int Remaining attempts (including this one)
     */
    public function getRemainingAttempts(): int
    {
        return $this->maxAttempts - $this->attemptNumber + 1;
    }

    /**
     * Check if this is the last retry attempt.
     *
     * @return bool True if last attempt, false otherwise
     */
    public function isLastAttempt(): bool
    {
        return $this->attemptNumber >= $this->maxAttempts;
    }

    /**
     * Get retry progress as a percentage.
     *
     * @return float Progress from 0.0 to 100.0
     */
    public function getProgress(): float
    {
        if ($this->maxAttempts <= 1) {
            return 100.0;
        }

        return ($this->attemptNumber / $this->maxAttempts) * 100.0;
    }

    /**
     * Get the delay in seconds (for readability).
     *
     * @return float Delay in seconds
     */
    public function getDelayInSeconds(): float
    {
        return $this->delay / 1000.0;
    }
}
