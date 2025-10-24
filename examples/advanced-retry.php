<?php

/**
 * Advanced Retry Logic Example
 *
 * This example demonstrates the advanced retry capabilities of Transport PHP,
 * including exponential backoff, custom retry conditions, and error handling.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Exceptions\RetryExhaustedException;
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\FixedDelayStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\TransportBuilder;

echo "=== Advanced Retry Logic Examples ===\n\n";

// Example 1: Exponential Backoff with Jitter
echo "1. Exponential Backoff with Jitter\n";
echo str_repeat('-', 50)."\n";

$transport = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withRetries(
        maxRetries: 3,
        strategy: new ExponentialBackoffStrategy(
            baseDelayMs: 1000,    // Start with 1 second
            multiplier: 2.0,       // Double each retry
            maxDelayMs: 30000,     // Cap at 30 seconds
            useJitter: true        // Add randomization to prevent thundering herd
        )
    )
    ->build();

echo "Configured with:\n";
echo "  - Max retries: 3\n";
echo "  - Base delay: 1000ms\n";
echo "  - Multiplier: 2.0\n";
echo "  - Max delay: 30000ms\n";
echo "  - Jitter: enabled\n\n";

// Example 2: Fixed Delay Strategy
echo "2. Fixed Delay Strategy\n";
echo str_repeat('-', 50)."\n";

$transport2 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withRetries(
        maxRetries: 3,
        strategy: new FixedDelayStrategy(delayMs: 2000) // Wait 2 seconds between retries
    )
    ->build();

echo "Configured with:\n";
echo "  - Max retries: 3\n";
echo "  - Fixed delay: 2000ms\n\n";

// Example 3: Custom Retry Conditions
echo "3. Custom Retry Conditions\n";
echo str_repeat('-', 50)."\n";

$transport3 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withRetries(
        maxRetries: 5,
        strategy: new ExponentialBackoffStrategy,
        condition: RetryCondition::default()
            ->onStatusCodes([408, 429, 500, 502, 503, 504]) // Retry on specific status codes
    )
    ->build();

echo "Configured to retry on:\n";
echo "  - 408 Request Timeout\n";
echo "  - 429 Too Many Requests\n";
echo "  - 500 Internal Server Error\n";
echo "  - 502 Bad Gateway\n";
echo "  - 503 Service Unavailable\n";
echo "  - 504 Gateway Timeout\n\n";

// Example 4: Retry with Custom Condition Callback
echo "4. Retry with Custom Condition Callback\n";
echo str_repeat('-', 50)."\n";

$transport4 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withRetries(
        maxRetries: 3,
        strategy: new ExponentialBackoffStrategy,
        condition: RetryCondition::fromCallback(
            function (\Throwable $exception, \Farzai\Transport\Retry\RetryContext $context): bool {
                echo "  Retry attempt {$context->attempt}/{$context->maxAttempts}\n";
                echo "  Exception: {$exception->getMessage()}\n";

                // Retry only on network errors or 5xx responses
                if ($exception instanceof \Farzai\Transport\Exceptions\ServerException) {
                    echo "  Decision: RETRY (Server error)\n\n";

                    return true;
                }

                if ($exception instanceof \Farzai\Transport\Exceptions\NetworkException) {
                    echo "  Decision: RETRY (Network error)\n\n";

                    return true;
                }

                echo "  Decision: DO NOT RETRY\n\n";

                return false;
            }
        )
    )
    ->build();

echo "Custom retry logic:\n";
echo "  - Retry on ServerException (5xx)\n";
echo "  - Retry on NetworkException\n";
echo "  - Do not retry on ClientException (4xx)\n\n";

// Example 5: Handling Retry Exhaustion
echo "5. Handling Retry Exhaustion\n";
echo str_repeat('-', 50)."\n";

try {
    // This will likely succeed, but demonstrates the pattern
    $response = $transport->get('/posts/1')->send();
    echo "Request succeeded on first try\n";
    echo "Title: {$response->json('title')}\n\n";
} catch (RetryExhaustedException $e) {
    echo "❌ Request failed after {$e->getAttempts()} attempts\n";
    echo "Last error: {$e->getMessage()}\n\n";

    echo "Retry details:\n";
    foreach ($e->getRetryExceptions() as $attempt => $exception) {
        echo "  Attempt {$attempt}: {$exception->getMessage()}\n";
    }
    echo "\n";

    echo 'Delays used: '.implode(', ', $e->getDelaysUsed())." ms\n\n";
}

// Example 6: Demonstrating Retry with Valid Request
echo "6. Successful Request (No Retries Needed)\n";
echo str_repeat('-', 50)."\n";

try {
    $startTime = microtime(true);

    $response = $transport->get('/posts/1')->send();

    $duration = round((microtime(true) - $startTime) * 1000, 2);

    echo "✓ Request succeeded\n";
    echo "  Duration: {$duration}ms\n";
    echo "  Status: {$response->statusCode()}\n";
    echo "  Title: {$response->json('title')}\n\n";
} catch (RetryExhaustedException $e) {
    echo "❌ Failed after {$e->getAttempts()} attempts\n\n";
}

// Example 7: Retry Strategy Comparison
echo "7. Retry Strategy Comparison\n";
echo str_repeat('-', 50)."\n";

echo "Exponential Backoff delays (base=1000ms, multiplier=2.0):\n";
$exponential = new ExponentialBackoffStrategy(1000, 2.0, 30000, false);
for ($i = 0; $i < 5; $i++) {
    $context = new \Farzai\Transport\Retry\RetryContext(
        request: $transport->request()->uri('/test')->build(),
        attempt: $i,
        maxAttempts: 5
    );
    $delay = $exponential->getDelay($context);
    echo "  Attempt {$i}: {$delay}ms\n";
}
echo "\n";

echo "Fixed Delay (2000ms):\n";
$fixed = new FixedDelayStrategy(2000);
for ($i = 0; $i < 5; $i++) {
    $context = new \Farzai\Transport\Retry\RetryContext(
        request: $transport->request()->uri('/test')->build(),
        attempt: $i,
        maxAttempts: 5
    );
    $delay = $fixed->getDelay($context);
    echo "  Attempt {$i}: {$delay}ms\n";
}
echo "\n";

echo "=== Examples Complete ===\n";
