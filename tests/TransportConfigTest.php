<?php

declare(strict_types=1);

use Farzai\Transport\Middleware\MiddlewareInterface;
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\FixedDelayStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\TransportConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

describe('TransportConfig', function () {
    beforeEach(function () {
        $this->client = Mockery::mock(ClientInterface::class);
    });

    it('can be created with default values', function () {
        $config = new TransportConfig($this->client);

        expect($config->client)->toBe($this->client)
            ->and($config->logger)->toBeInstanceOf(NullLogger::class)
            ->and($config->baseUri)->toBe('')
            ->and($config->headers)->toBe([])
            ->and($config->timeout)->toBe(30)
            ->and($config->maxRetries)->toBe(0)
            ->and($config->retryStrategy)->toBeInstanceOf(ExponentialBackoffStrategy::class)
            ->and($config->retryCondition)->toBeInstanceOf(RetryCondition::class)
            ->and($config->middlewares)->toBe([]);
    });

    it('can be created with custom values', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $retryStrategy = new FixedDelayStrategy(1000);
        $retryCondition = new RetryCondition();
        $middleware = Mockery::mock(MiddlewareInterface::class);

        $config = new TransportConfig(
            client: $this->client,
            logger: $logger,
            baseUri: 'https://api.example.com',
            headers: ['Authorization' => 'Bearer token'],
            timeout: 60,
            maxRetries: 3,
            retryStrategy: $retryStrategy,
            retryCondition: $retryCondition,
            middlewares: [$middleware]
        );

        expect($config->client)->toBe($this->client)
            ->and($config->logger)->toBe($logger)
            ->and($config->baseUri)->toBe('https://api.example.com')
            ->and($config->headers)->toBe(['Authorization' => 'Bearer token'])
            ->and($config->timeout)->toBe(60)
            ->and($config->maxRetries)->toBe(3)
            ->and($config->retryStrategy)->toBe($retryStrategy)
            ->and($config->retryCondition)->toBe($retryCondition)
            ->and($config->middlewares)->toHaveCount(1);
    });

    it('throws exception for negative timeout', function () {
        expect(fn () => new TransportConfig($this->client, timeout: -1))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be greater than or equal to 0.');
    });

    it('throws exception for negative max retries', function () {
        expect(fn () => new TransportConfig($this->client, maxRetries: -5))
            ->toThrow(InvalidArgumentException::class, 'Max retries must be greater than or equal to 0.');
    });

    it('throws exception for invalid middleware', function () {
        $invalidMiddleware = new stdClass();

        expect(fn () => new TransportConfig($this->client, middlewares: [$invalidMiddleware]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('allows zero timeout', function () {
        $config = new TransportConfig($this->client, timeout: 0);

        expect($config->timeout)->toBe(0);
    });

    it('allows zero max retries', function () {
        $config = new TransportConfig($this->client, maxRetries: 0);

        expect($config->maxRetries)->toBe(0);
    });

    it('withHeaders creates new instance with merged headers', function () {
        $config = new TransportConfig($this->client, headers: ['Accept' => 'application/json']);
        $newConfig = $config->withHeaders(['Authorization' => 'Bearer token']);

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->headers)->toBe([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token',
            ])
            ->and($config->headers)->toBe(['Accept' => 'application/json']);
    });

    it('withHeaders overwrites existing headers', function () {
        $config = new TransportConfig($this->client, headers: ['Accept' => 'application/json']);
        $newConfig = $config->withHeaders(['Accept' => 'text/html']);

        expect($newConfig->headers)->toBe(['Accept' => 'text/html']);
    });

    it('withBaseUri creates new instance with updated base URI', function () {
        $config = new TransportConfig($this->client, baseUri: 'https://api.example.com');
        $newConfig = $config->withBaseUri('https://api.newdomain.com');

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->baseUri)->toBe('https://api.newdomain.com')
            ->and($config->baseUri)->toBe('https://api.example.com');
    });

    it('withTimeout creates new instance with updated timeout', function () {
        $config = new TransportConfig($this->client, timeout: 30);
        $newConfig = $config->withTimeout(60);

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->timeout)->toBe(60)
            ->and($config->timeout)->toBe(30);
    });

    it('withTimeout validates timeout value', function () {
        $config = new TransportConfig($this->client);

        expect(fn () => $config->withTimeout(-1))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be greater than or equal to 0.');
    });

    it('withRetries creates new instance with updated retry settings', function () {
        $config = new TransportConfig($this->client, maxRetries: 0);
        $newStrategy = new FixedDelayStrategy(2000);
        $newCondition = new RetryCondition();

        $newConfig = $config->withRetries(5, $newStrategy, $newCondition);

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->maxRetries)->toBe(5)
            ->and($newConfig->retryStrategy)->toBe($newStrategy)
            ->and($newConfig->retryCondition)->toBe($newCondition)
            ->and($config->maxRetries)->toBe(0);
    });

    it('withRetries can update max retries only', function () {
        $originalStrategy = new FixedDelayStrategy(1000);
        $originalCondition = new RetryCondition();
        $config = new TransportConfig(
            $this->client,
            maxRetries: 3,
            retryStrategy: $originalStrategy,
            retryCondition: $originalCondition
        );

        $newConfig = $config->withRetries(10);

        expect($newConfig->maxRetries)->toBe(10)
            ->and($newConfig->retryStrategy)->toBe($originalStrategy)
            ->and($newConfig->retryCondition)->toBe($originalCondition);
    });

    it('withRetries validates max retries value', function () {
        $config = new TransportConfig($this->client);

        expect(fn () => $config->withRetries(-1))
            ->toThrow(InvalidArgumentException::class, 'Max retries must be greater than or equal to 0.');
    });

    it('withMiddleware creates new instance with added middleware', function () {
        $middleware1 = Mockery::mock(MiddlewareInterface::class);
        $middleware2 = Mockery::mock(MiddlewareInterface::class);

        $config = new TransportConfig($this->client, middlewares: [$middleware1]);
        $newConfig = $config->withMiddleware($middleware2);

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->middlewares)->toHaveCount(2)
            ->and($newConfig->middlewares[0])->toBe($middleware1)
            ->and($newConfig->middlewares[1])->toBe($middleware2)
            ->and($config->middlewares)->toHaveCount(1);
    });

    it('withMiddleware preserves middleware order', function () {
        $middleware1 = Mockery::mock(MiddlewareInterface::class);
        $middleware2 = Mockery::mock(MiddlewareInterface::class);
        $middleware3 = Mockery::mock(MiddlewareInterface::class);

        $config = new TransportConfig($this->client);
        $config = $config->withMiddleware($middleware1);
        $config = $config->withMiddleware($middleware2);
        $config = $config->withMiddleware($middleware3);

        expect($config->middlewares)->toHaveCount(3)
            ->and($config->middlewares[0])->toBe($middleware1)
            ->and($config->middlewares[1])->toBe($middleware2)
            ->and($config->middlewares[2])->toBe($middleware3);
    });

    it('withLogger creates new instance with updated logger', function () {
        $logger1 = Mockery::mock(LoggerInterface::class);
        $logger2 = Mockery::mock(LoggerInterface::class);

        $config = new TransportConfig($this->client, logger: $logger1);
        $newConfig = $config->withLogger($logger2);

        expect($newConfig)->not->toBe($config)
            ->and($newConfig->logger)->toBe($logger2)
            ->and($config->logger)->toBe($logger1);
    });

    it('is immutable when using with methods', function () {
        $config = new TransportConfig(
            client: $this->client,
            baseUri: 'https://api.example.com',
            headers: ['Accept' => 'application/json'],
            timeout: 30,
            maxRetries: 3
        );

        $config->withHeaders(['Authorization' => 'Bearer token']);
        $config->withBaseUri('https://new.example.com');
        $config->withTimeout(60);
        $config->withRetries(5);

        // Original config should remain unchanged
        expect($config->headers)->toBe(['Accept' => 'application/json'])
            ->and($config->baseUri)->toBe('https://api.example.com')
            ->and($config->timeout)->toBe(30)
            ->and($config->maxRetries)->toBe(3);
    });

    it('can chain multiple with methods', function () {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $config = new TransportConfig($this->client);
        $newConfig = $config
            ->withHeaders(['Accept' => 'application/json'])
            ->withBaseUri('https://api.example.com')
            ->withTimeout(60)
            ->withRetries(3)
            ->withMiddleware($middleware)
            ->withLogger($logger);

        expect($newConfig->headers)->toBe(['Accept' => 'application/json'])
            ->and($newConfig->baseUri)->toBe('https://api.example.com')
            ->and($newConfig->timeout)->toBe(60)
            ->and($newConfig->maxRetries)->toBe(3)
            ->and($newConfig->middlewares)->toHaveCount(1)
            ->and($newConfig->logger)->toBe($logger);
    });
});
