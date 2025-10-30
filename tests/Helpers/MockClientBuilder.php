<?php

declare(strict_types=1);

namespace Farzai\Transport\Tests\Helpers;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Builder for creating PSR-18 client mocks.
 *
 * Design Pattern: Builder Pattern
 * - Fluent API for mock configuration
 * - Reduces test setup boilerplate
 * - Makes tests more readable
 *
 * @example
 * ```php
 * $client = MockClientBuilder::create()
 *     ->willReturn(200, ['Content-Type' => 'application/json'], '{"success":true}')
 *     ->build();
 * ```
 */
final class MockClientBuilder
{
    private ?ResponseInterface $response = null;

    private ?\Throwable $exception = null;

    /** @var callable|null */
    private $callback = null;

    private int $callCount = 1;

    /**
     * Create a new mock client builder.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Set the response to return.
     */
    public function willReturn(
        int $status = 200,
        array $headers = [],
        string $body = ''
    ): self {
        $this->response = new GuzzleResponse($status, $headers, $body);

        return $this;
    }

    /**
     * Set a custom response object.
     */
    public function willReturnResponse(ResponseInterface $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Set an exception to throw.
     */
    public function willThrow(\Throwable $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * Set a callback to determine response.
     */
    public function willCall(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Set expected call count.
     */
    public function times(int $count): self
    {
        $this->callCount = $count;

        return $this;
    }

    /**
     * Build the mock client.
     */
    public function build(): ClientInterface
    {
        $mock = Mockery::mock(ClientInterface::class);

        $expectation = $mock->shouldReceive('sendRequest')
            ->times($this->callCount)
            ->with(Mockery::type(RequestInterface::class));

        if ($this->callback !== null) {
            $expectation->andReturnUsing($this->callback);
        } elseif ($this->exception !== null) {
            $expectation->andThrow($this->exception);
        } else {
            $expectation->andReturn($this->response ?? new GuzzleResponse(200));
        }

        return $mock;
    }
}
