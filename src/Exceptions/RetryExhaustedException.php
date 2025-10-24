<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Farzai\Transport\Retry\RetryContext;
use Throwable;

class RetryExhaustedException extends TransportException
{
    public function __construct(
        string $message,
        public readonly RetryContext $context,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get all exceptions that occurred during retries.
     *
     * @return array<int, Throwable>
     */
    public function getRetryExceptions(): array
    {
        return $this->context->exceptions;
    }

    /**
     * Get the delays that were used between retries.
     *
     * @return array<int, int>
     */
    public function getDelaysUsed(): array
    {
        return $this->context->delaysUsed;
    }

    /**
     * Get the number of attempts made.
     */
    public function getAttempts(): int
    {
        return $this->context->attempt;
    }
}
