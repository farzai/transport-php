<?php

declare(strict_types=1);

namespace Farzai\Transport\Retry;

class ExponentialBackoffStrategy implements RetryStrategyInterface
{
    /**
     * @param  int  $baseDelayMs  Base delay in milliseconds
     * @param  float  $multiplier  Exponential multiplier
     * @param  int  $maxDelayMs  Maximum delay in milliseconds
     * @param  bool  $useJitter  Add random jitter to prevent thundering herd
     */
    public function __construct(
        private readonly int $baseDelayMs = 1000,
        private readonly float $multiplier = 2.0,
        private readonly int $maxDelayMs = 30000,
        private readonly bool $useJitter = true
    ) {}

    public function getDelay(RetryContext $context): int
    {
        // Calculate exponential delay: baseDelay * (multiplier ^ attempt)
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** $context->attempt));

        // Cap at maximum delay
        $delay = min($delay, $this->maxDelayMs);

        // Add jitter if enabled (randomize between 0% and 100% of calculated delay)
        if ($this->useJitter) {
            $delay = (int) ($delay * (mt_rand(0, 100) / 100));
        }

        return $delay;
    }
}
