<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Middleware\MiddlewareInterface;
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\Retry\RetryStrategyInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TransportConfig
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<MiddlewareInterface>  $middlewares
     */
    public function __construct(
        public readonly ClientInterface $client,
        public readonly LoggerInterface $logger = new NullLogger,
        public readonly string $baseUri = '',
        public readonly array $headers = [],
        public readonly int $timeout = 30,
        public readonly int $maxRetries = 0,
        public readonly RetryStrategyInterface $retryStrategy = new ExponentialBackoffStrategy,
        public readonly RetryCondition $retryCondition = new RetryCondition,
        public readonly array $middlewares = []
    ) {
        $this->validate();
    }

    /**
     * Validate the configuration.
     */
    private function validate(): void
    {
        if ($this->timeout < 0) {
            throw new \InvalidArgumentException('Timeout must be greater than or equal to 0.');
        }

        if ($this->maxRetries < 0) {
            throw new \InvalidArgumentException('Max retries must be greater than or equal to 0.');
        }

        foreach ($this->middlewares as $middleware) {
            if (! $middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Middleware must implement %s', MiddlewareInterface::class)
                );
            }
        }
    }

    /**
     * Create a new configuration with modified values.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            client: $this->client,
            logger: $this->logger,
            baseUri: $this->baseUri,
            headers: array_merge($this->headers, $headers),
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy,
            retryCondition: $this->retryCondition,
            middlewares: $this->middlewares
        );
    }

    /**
     * Create a new configuration with a modified base URI.
     */
    public function withBaseUri(string $baseUri): self
    {
        return new self(
            client: $this->client,
            logger: $this->logger,
            baseUri: $baseUri,
            headers: $this->headers,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy,
            retryCondition: $this->retryCondition,
            middlewares: $this->middlewares
        );
    }

    /**
     * Create a new configuration with a modified timeout.
     */
    public function withTimeout(int $timeout): self
    {
        return new self(
            client: $this->client,
            logger: $this->logger,
            baseUri: $this->baseUri,
            headers: $this->headers,
            timeout: $timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy,
            retryCondition: $this->retryCondition,
            middlewares: $this->middlewares
        );
    }

    /**
     * Create a new configuration with modified retry settings.
     */
    public function withRetries(
        int $maxRetries,
        ?RetryStrategyInterface $strategy = null,
        ?RetryCondition $condition = null
    ): self {
        return new self(
            client: $this->client,
            logger: $this->logger,
            baseUri: $this->baseUri,
            headers: $this->headers,
            timeout: $this->timeout,
            maxRetries: $maxRetries,
            retryStrategy: $strategy ?? $this->retryStrategy,
            retryCondition: $condition ?? $this->retryCondition,
            middlewares: $this->middlewares
        );
    }

    /**
     * Create a new configuration with additional middleware.
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        return new self(
            client: $this->client,
            logger: $this->logger,
            baseUri: $this->baseUri,
            headers: $this->headers,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy,
            retryCondition: $this->retryCondition,
            middlewares: [...$this->middlewares, $middleware]
        );
    }

    /**
     * Create a new configuration with modified logger.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return new self(
            client: $this->client,
            logger: $logger,
            baseUri: $this->baseUri,
            headers: $this->headers,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy,
            retryCondition: $this->retryCondition,
            middlewares: $this->middlewares
        );
    }
}
