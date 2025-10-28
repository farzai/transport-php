<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Cookie\CookieJar;
use Farzai\Transport\Events\EventDispatcher;
use Farzai\Transport\Events\EventDispatcherInterface;
use Farzai\Transport\Events\EventInterface;
use Farzai\Transport\Factory\ClientFactory;
use Farzai\Transport\Middleware\CookieMiddleware;
use Farzai\Transport\Middleware\EventMiddleware;
use Farzai\Transport\Middleware\LoggingMiddleware;
use Farzai\Transport\Middleware\MiddlewareInterface;
use Farzai\Transport\Middleware\RetryMiddleware;
use Farzai\Transport\Middleware\TimeoutMiddleware;
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\Retry\RetryStrategyInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TransportBuilder
{
    private ?ClientInterface $client = null;

    private ?LoggerInterface $logger = null;

    private string $baseUri = '';

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private int $timeout = 30;

    private int $maxRetries = 0;

    private ?RetryStrategyInterface $retryStrategy = null;

    private ?RetryCondition $retryCondition = null;

    /**
     * @var array<MiddlewareInterface>
     */
    private array $middlewares = [];

    private bool $useDefaultMiddlewares = true;

    private ?CookieJar $cookieJar = null;

    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Create a new builder instance.
     */
    public static function make(): static
    {
        return new self;
    }

    /**
     * Set the HTTP client.
     */
    public function setClient(ClientInterface $client): self
    {
        $clone = clone $this;
        $clone->client = $client;

        return $clone;
    }

    /**
     * Set the logger.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $clone = clone $this;
        $clone->logger = $logger;

        return $clone;
    }

    /**
     * Set the base URI.
     */
    public function withBaseUri(string $uri): self
    {
        $clone = clone $this;
        $clone->baseUri = $uri;

        return $clone;
    }

    /**
     * Set default headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    /**
     * Set the timeout in seconds.
     */
    public function withTimeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    /**
     * Configure retry behavior.
     */
    public function withRetries(
        int $maxRetries,
        ?RetryStrategyInterface $strategy = null,
        ?RetryCondition $condition = null
    ): self {
        $clone = clone $this;
        $clone->maxRetries = $maxRetries;
        $clone->retryStrategy = $strategy;
        $clone->retryCondition = $condition;

        return $clone;
    }

    /**
     * Add a custom middleware.
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $clone = clone $this;
        $clone->middlewares[] = $middleware;

        return $clone;
    }

    /**
     * Disable default middlewares (logging, timeout, retry).
     */
    public function withoutDefaultMiddlewares(): self
    {
        $clone = clone $this;
        $clone->useDefaultMiddlewares = false;

        return $clone;
    }

    /**
     * Enable automatic cookie handling with a cookie jar.
     *
     * @param  CookieJar|null  $cookieJar  Optional cookie jar (creates new if null)
     * @return $this
     */
    public function withCookieJar(?CookieJar $cookieJar = null): self
    {
        $clone = clone $this;
        $clone->cookieJar = $cookieJar ?? new CookieJar;

        return $clone;
    }

    /**
     * Enable automatic cookie handling (shortcut).
     *
     * @return $this
     */
    public function withCookies(): self
    {
        return $this->withCookieJar();
    }

    /**
     * Configure event dispatcher for HTTP lifecycle monitoring.
     *
     * @param  EventDispatcherInterface|null  $dispatcher  Optional custom event dispatcher
     * @return $this
     */
    public function withEventDispatcher(?EventDispatcherInterface $dispatcher = null): self
    {
        $clone = clone $this;
        $clone->eventDispatcher = $dispatcher ?? new EventDispatcher;

        return $clone;
    }

    /**
     * Add an event listener for HTTP lifecycle events.
     *
     * This is a convenience method that creates an EventDispatcher if needed
     * and registers the listener.
     *
     * @param  class-string<EventInterface>  $eventClass  The event class to listen for
     * @param  callable(EventInterface): void  $listener  The listener callable
     * @return $this
     *
     * @example
     * ```php
     * $transport = TransportBuilder::make()
     *     ->addEventListener(ResponseReceivedEvent::class, function ($event) {
     *         echo "Request completed in {$event->getDuration()}ms\n";
     *     })
     *     ->build();
     * ```
     */
    public function addEventListener(string $eventClass, callable $listener): self
    {
        $clone = clone $this;

        // Create dispatcher if not exists
        if ($clone->eventDispatcher === null) {
            $clone->eventDispatcher = new EventDispatcher;
        }

        $clone->eventDispatcher->addEventListener($eventClass, $listener);

        return $clone;
    }

    /**
     * Get the configured client.
     */
    public function getClient(): ?ClientInterface
    {
        return $this->client;
    }

    /**
     * Get the configured logger.
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Build the transport with configured settings.
     *
     * Auto-detects PSR-18 HTTP client if none is provided.
     * Discovery order: Symfony HTTP Client → Guzzle → Other PSR-18 clients
     */
    public function build(): Transport
    {
        $logger = $this->logger ?? new NullLogger;

        // Auto-detect client if not explicitly set
        // This allows users to use any PSR-18 client without configuration
        $client = $this->client ?? ClientFactory::create($logger);

        $config = new TransportConfig(
            client: $client,
            logger: $logger,
            baseUri: $this->baseUri,
            headers: $this->headers,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            retryStrategy: $this->retryStrategy ?? new ExponentialBackoffStrategy,
            retryCondition: $this->retryCondition ?? RetryCondition::default(),
            middlewares: $this->buildMiddlewares($logger),
            eventDispatcher: $this->eventDispatcher
        );

        return new Transport($config);
    }

    /**
     * Build the middleware stack.
     *
     * @return array<MiddlewareInterface>
     */
    private function buildMiddlewares(LoggerInterface $logger): array
    {
        $middlewares = [];

        // Add event middleware first if configured (to track entire lifecycle)
        if ($this->eventDispatcher !== null) {
            $middlewares[] = new EventMiddleware($this->eventDispatcher);
        }

        // Add cookie middleware early if configured
        if ($this->cookieJar !== null) {
            $middlewares[] = new CookieMiddleware($this->cookieJar);
        }

        if ($this->useDefaultMiddlewares) {
            // Add logging middleware
            $middlewares[] = new LoggingMiddleware($logger);

            // Add timeout middleware if configured
            if ($this->timeout > 0) {
                $middlewares[] = new TimeoutMiddleware($this->timeout);
            }

            // Add retry middleware if configured
            if ($this->maxRetries > 0) {
                $middlewares[] = new RetryMiddleware(
                    maxAttempts: $this->maxRetries,
                    strategy: $this->retryStrategy ?? new ExponentialBackoffStrategy,
                    condition: $this->retryCondition ?? RetryCondition::default(),
                    eventDispatcher: $this->eventDispatcher
                );
            }
        }

        // Add custom middlewares
        return array_merge($middlewares, $this->middlewares);
    }
}
