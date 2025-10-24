<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * Process the request and return a response.
     *
     * @param  callable(RequestInterface): ResponseInterface  $next
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}
