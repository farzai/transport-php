<?php

declare(strict_types=1);

namespace Farzai\Transport\Retry;

use Psr\Http\Message\RequestInterface;
use Throwable;

class RetryContext
{
    /**
     * @param  array<int, int>  $delaysUsed  Delays in milliseconds that were used for each retry
     * @param  array<int, Throwable>  $exceptions  Exceptions encountered during retries
     */
    public function __construct(
        public readonly RequestInterface $request,
        public readonly int $attempt,
        public readonly int $maxAttempts,
        public readonly ?Throwable $lastException = null,
        public readonly array $delaysUsed = [],
        public readonly array $exceptions = []
    ) {}

    /**
     * Check if we have retries remaining.
     */
    public function hasRetriesLeft(): bool
    {
        return $this->attempt < $this->maxAttempts;
    }

    /**
     * Get the number of retries remaining.
     */
    public function retriesLeft(): int
    {
        return max(0, $this->maxAttempts - $this->attempt);
    }

    /**
     * Create a new context for the next retry attempt.
     */
    public function nextAttempt(Throwable $exception, int $delayUsed): self
    {
        return new self(
            request: $this->request,
            attempt: $this->attempt + 1,
            maxAttempts: $this->maxAttempts,
            lastException: $exception,
            delaysUsed: [...$this->delaysUsed, $delayUsed],
            exceptions: [...$this->exceptions, $exception]
        );
    }
}
