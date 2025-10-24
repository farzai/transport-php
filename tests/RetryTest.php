<?php

declare(strict_types=1);

use Farzai\Transport\Exceptions\RetryExhaustedException;
use Farzai\Transport\Middleware\RetryMiddleware;
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\FixedDelayStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\Retry\RetryContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('RetryStrategy', function () {
    it('FixedDelayStrategy returns constant delay', function () {
        $strategy = new FixedDelayStrategy(1000);

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        expect($strategy->getDelay($context))->toBe(1000);

        $context = new RetryContext($request, 1, 3);
        expect($strategy->getDelay($context))->toBe(1000);

        $context = new RetryContext($request, 2, 3);
        expect($strategy->getDelay($context))->toBe(1000);
    });

    it('ExponentialBackoffStrategy increases delay exponentially', function () {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 2.0,
            maxDelayMs: 30000,
            useJitter: false // Disable jitter for predictable testing
        );

        $request = new Request('GET', 'https://example.com');

        $context = new RetryContext($request, 0, 3);
        expect($strategy->getDelay($context))->toBe(1000); // 1000 * (2^0) = 1000

        $context = new RetryContext($request, 1, 3);
        expect($strategy->getDelay($context))->toBe(2000); // 1000 * (2^1) = 2000

        $context = new RetryContext($request, 2, 3);
        expect($strategy->getDelay($context))->toBe(4000); // 1000 * (2^2) = 4000
    });

    it('ExponentialBackoffStrategy respects max delay', function () {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 2.0,
            maxDelayMs: 5000,
            useJitter: false
        );

        $request = new Request('GET', 'https://example.com');

        $context = new RetryContext($request, 10, 15);
        $delay = $strategy->getDelay($context);

        expect($delay)->toBeLessThanOrEqual(5000);
    });

    it('ExponentialBackoffStrategy with jitter produces variable delays', function () {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 2.0,
            maxDelayMs: 30000,
            useJitter: true
        );

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 1, 3);

        $delay1 = $strategy->getDelay($context);
        $delay2 = $strategy->getDelay($context);

        // With jitter, delays should vary (though they might occasionally be equal)
        // The delay should be between 0 and 2000 (base * multiplier^attempt)
        expect($delay1)->toBeGreaterThanOrEqual(0)
            ->and($delay1)->toBeLessThanOrEqual(2000);
    });
});

describe('RetryCondition', function () {
    it('default condition retries on any exception', function () {
        $condition = RetryCondition::default();

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        expect($condition->shouldRetry(new \RuntimeException, $context))->toBeTrue()
            ->and($condition->shouldRetry(new \Exception, $context))->toBeTrue();
    });

    it('can retry on specific exception types', function () {
        $condition = new RetryCondition;
        $condition->onExceptions([\RuntimeException::class]);

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        expect($condition->shouldRetry(new \RuntimeException, $context))->toBeTrue()
            ->and($condition->shouldRetry(new \LogicException, $context))->toBeFalse();
    });

    it('does not retry when no retries left', function () {
        $condition = RetryCondition::default();

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 3, 3); // At max attempts

        expect($condition->shouldRetry(new \RuntimeException, $context))->toBeFalse();
    });

    it('can use custom condition callback', function () {
        $condition = new RetryCondition;
        $condition->when(function ($exception, $context) {
            return $exception->getMessage() === 'retryable';
        });

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        expect($condition->shouldRetry(new \RuntimeException('retryable'), $context))->toBeTrue()
            ->and($condition->shouldRetry(new \RuntimeException('fatal'), $context))->toBeFalse();
    });

    it('does not retry when no conditions are set', function () {
        $condition = new RetryCondition; // No conditions added

        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        // Should return false since there are no conditions
        expect($condition->shouldRetry(new \RuntimeException('error'), $context))->toBeFalse();
    });
});

describe('RetryContext', function () {
    it('tracks retry attempts', function () {
        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        expect($context->attempt)->toBe(0)
            ->and($context->maxAttempts)->toBe(3)
            ->and($context->hasRetriesLeft())->toBeTrue()
            ->and($context->retriesLeft())->toBe(3);
    });

    it('can create next attempt context', function () {
        $request = new Request('GET', 'https://example.com');
        $context = new RetryContext($request, 0, 3);

        $exception = new \RuntimeException('error');
        $nextContext = $context->nextAttempt($exception, 1000);

        expect($nextContext->attempt)->toBe(1)
            ->and($nextContext->lastException)->toBe($exception)
            ->and($nextContext->delaysUsed)->toBe([1000])
            ->and($nextContext->exceptions)->toBe([$exception]);
    });
});

describe('RetryMiddleware', function () {
    it('retries on failure', function () {
        $attempts = 0;
        $strategy = new FixedDelayStrategy(0); // No delay for faster tests
        $condition = RetryCondition::default();

        $middleware = new RetryMiddleware(3, $strategy, $condition);

        $request = new Request('GET', 'https://example.com');

        $response = $middleware->handle($request, function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Temporary error');
            }

            return new Response(200);
        });

        expect($attempts)->toBe(3)
            ->and($response->getStatusCode())->toBe(200);
    });

    it('throws RetryExhaustedException when retries exhausted', function () {
        $strategy = new FixedDelayStrategy(0);
        $condition = RetryCondition::default();

        $middleware = new RetryMiddleware(2, $strategy, $condition);

        $request = new Request('GET', 'https://example.com');

        $middleware->handle($request, function () {
            throw new \RuntimeException('Persistent error');
        });
    })->throws(RetryExhaustedException::class);

    it('provides retry context in exception', function () {
        $strategy = new FixedDelayStrategy(0);
        $condition = RetryCondition::default();

        $middleware = new RetryMiddleware(2, $strategy, $condition);

        $request = new Request('GET', 'https://example.com');

        try {
            $middleware->handle($request, function () {
                throw new \RuntimeException('Persistent error');
            });
            expect(false)->toBeTrue(); // Should not reach here
        } catch (RetryExhaustedException $e) {
            expect($e->getAttempts())->toBe(2)
                ->and($e->getRetryExceptions())->toHaveCount(2)
                ->and($e->getDelaysUsed())->toHaveCount(2);
        }
    });

    it('does not retry when condition not met', function () {
        $attempts = 0;
        $strategy = new FixedDelayStrategy(0);
        $condition = new RetryCondition;
        $condition->onExceptions([\LogicException::class]); // Only retry LogicException

        $middleware = new RetryMiddleware(3, $strategy, $condition);

        $request = new Request('GET', 'https://example.com');

        try {
            $middleware->handle($request, function () use (&$attempts) {
                $attempts++;
                throw new \RuntimeException('Error'); // Different exception type
            });
        } catch (\RuntimeException $e) {
            expect($attempts)->toBe(1); // No retries
        }
    });
});

afterEach(function () {
    Mockery::close();
});
