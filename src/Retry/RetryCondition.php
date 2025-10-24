<?php

declare(strict_types=1);

namespace Farzai\Transport\Retry;

use Throwable;

class RetryCondition
{
    /**
     * @var array<callable(Throwable, RetryContext): bool>
     */
    private array $conditions = [];

    /**
     * Create a default retry condition that retries on any exception.
     */
    public static function default(): self
    {
        $condition = new self;
        $condition->onAnyException();

        return $condition;
    }

    /**
     * Retry on any exception.
     */
    public function onAnyException(): self
    {
        $this->conditions[] = fn (Throwable $exception, RetryContext $context) => true;

        return $this;
    }

    /**
     * Retry only on specific exception types.
     *
     * @param  array<class-string<Throwable>>  $exceptionClasses
     */
    public function onExceptions(array $exceptionClasses): self
    {
        $this->conditions[] = function (Throwable $exception) use ($exceptionClasses) {
            foreach ($exceptionClasses as $class) {
                if ($exception instanceof $class) {
                    return true;
                }
            }

            return false;
        };

        return $this;
    }

    /**
     * Add a custom retry condition.
     *
     * @param  callable(Throwable, RetryContext): bool  $callback
     */
    public function when(callable $callback): self
    {
        $this->conditions[] = $callback;

        return $this;
    }

    /**
     * Check if we should retry based on the exception and context.
     */
    public function shouldRetry(Throwable $exception, RetryContext $context): bool
    {
        if (! $context->hasRetriesLeft()) {
            return false;
        }

        // If no conditions, don't retry
        if (empty($this->conditions)) {
            return false;
        }

        // Check if any condition matches
        foreach ($this->conditions as $condition) {
            if ($condition($exception, $context)) {
                return true;
            }
        }

        return false;
    }
}
