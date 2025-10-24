<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Farzai\Transport\Exceptions\RetryExhaustedException;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\Retry\RetryContext;
use Farzai\Transport\Retry\RetryStrategyInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly RetryStrategyInterface $strategy,
        private readonly RetryCondition $condition
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $context = new RetryContext(
            request: $request,
            attempt: 0,
            maxAttempts: $this->maxAttempts
        );

        while (true) {
            try {
                return $next($request);
            } catch (Throwable $exception) {
                // Check if we should retry
                if (! $this->condition->shouldRetry($exception, $context)) {
                    if ($context->attempt > 0) {
                        // We attempted retries but exhausted them
                        throw new RetryExhaustedException(
                            message: sprintf(
                                'Request failed after %d attempts. Last error: %s',
                                $context->attempt + 1,
                                $exception->getMessage()
                            ),
                            context: $context,
                            previous: $exception
                        );
                    }

                    // First attempt failed and no retries configured/allowed
                    throw $exception;
                }

                // Calculate delay and update context
                $delay = $this->strategy->getDelay($context);
                $context = $context->nextAttempt($exception, $delay);

                // Sleep before retry (convert milliseconds to microseconds)
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }
    }
}
