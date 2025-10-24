<?php

declare(strict_types=1);

namespace Farzai\Transport\Retry;

class FixedDelayStrategy implements RetryStrategyInterface
{
    /**
     * @param  int  $delayMs  Delay in milliseconds
     */
    public function __construct(
        private readonly int $delayMs = 1000
    ) {}

    public function getDelay(RetryContext $context): int
    {
        return $this->delayMs;
    }
}
