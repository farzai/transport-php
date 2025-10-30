<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Farzai\Transport\Events\EventDispatcherInterface;
use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Events\RetryAttemptEvent;
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
        private readonly RetryCondition $condition,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    /**
     * @throws \Farzai\Transport\Exceptions\RetryExhaustedException
     */
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
                // Dispatch RequestFailedEvent for each failure attempt
                if ($this->eventDispatcher !== null) {
                    $this->eventDispatcher->dispatch(
                        new RequestFailedEvent(
                            $request,
                            $exception,
                            $context->attempt + 1, // 1-based attempt number
                            microtime(true)
                        )
                    );
                }

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

                // Dispatch RetryAttemptEvent before retry
                if ($this->eventDispatcher !== null) {
                    $this->eventDispatcher->dispatch(
                        new RetryAttemptEvent(
                            $request,
                            $exception,
                            $context->attempt + 1, // Current attempt number (after increment)
                            $this->maxAttempts,
                            $delay,
                            microtime(true)
                        )
                    );
                }

                // Sleep before retry (convert milliseconds to microseconds)
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }
    }
}
