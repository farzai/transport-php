<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TimeoutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $timeoutSeconds
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add timeout header for PSR-18 client implementation to use
        // The actual timeout enforcement depends on the underlying HTTP client
        $request = $request->withHeader('X-Timeout', (string) $this->timeoutSeconds);

        return $next($request);
    }

    /**
     * Get the timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeoutSeconds;
    }
}
