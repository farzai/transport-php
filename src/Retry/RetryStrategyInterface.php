<?php

declare(strict_types=1);

namespace Farzai\Transport\Retry;

interface RetryStrategyInterface
{
    /**
     * Calculate the delay in milliseconds before the next retry.
     *
     * @return int Delay in milliseconds
     */
    public function getDelay(RetryContext $context): int;
}
