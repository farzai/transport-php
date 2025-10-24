<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MiddlewareStack
{
    /**
     * @var array<MiddlewareInterface>
     */
    private array $middlewares = [];

    /**
     * @param  array<MiddlewareInterface>  $middlewares
     */
    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    /**
     * Add a middleware to the stack.
     */
    public function push(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Process the request through the middleware stack.
     *
     * @param  callable(RequestInterface): ResponseInterface  $core
     */
    public function handle(RequestInterface $request, callable $core): ResponseInterface
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn (callable $next, MiddlewareInterface $middleware) => fn (RequestInterface $req) => $middleware->handle($req, $next),
            $core
        );

        return $pipeline($request);
    }
}
