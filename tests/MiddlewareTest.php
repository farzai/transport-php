<?php

declare(strict_types=1);

use Farzai\Transport\Middleware\LoggingMiddleware;
use Farzai\Transport\Middleware\MiddlewareStack;
use Farzai\Transport\Middleware\TimeoutMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

describe('MiddlewareStack', function () {
    it('can execute middleware in correct order', function () {
        $order = [];

        $middleware1 = new class($order) implements \Farzai\Transport\Middleware\MiddlewareInterface
        {
            public function __construct(private array &$order) {}

            public function handle(\Psr\Http\Message\RequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                $this->order[] = 'before-1';
                $response = $next($request);
                $this->order[] = 'after-1';

                return $response;
            }
        };

        $middleware2 = new class($order) implements \Farzai\Transport\Middleware\MiddlewareInterface
        {
            public function __construct(private array &$order) {}

            public function handle(\Psr\Http\Message\RequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                $this->order[] = 'before-2';
                $response = $next($request);
                $this->order[] = 'after-2';

                return $response;
            }
        };

        $stack = new MiddlewareStack([$middleware1, $middleware2]);

        $request = new Request('GET', 'https://example.com');
        $stack->handle($request, function ($req) use (&$order) {
            $order[] = 'core';

            return new Response(200);
        });

        expect($order)->toBe(['before-1', 'before-2', 'core', 'after-2', 'after-1']);
    });

    it('can modify request in middleware', function () {
        $middleware = new class implements \Farzai\Transport\Middleware\MiddlewareInterface
        {
            public function handle(\Psr\Http\Message\RequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                $request = $request->withHeader('X-Modified', 'true');

                return $next($request);
            }
        };

        $stack = new MiddlewareStack([$middleware]);

        $request = new Request('GET', 'https://example.com');
        $stack->handle($request, function ($req) {
            expect($req->getHeaderLine('X-Modified'))->toBe('true');

            return new Response(200);
        });
    });

    it('can modify response in middleware', function () {
        $middleware = new class implements \Farzai\Transport\Middleware\MiddlewareInterface
        {
            public function handle(\Psr\Http\Message\RequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                $response = $next($request);

                return $response->withHeader('X-Modified', 'true');
            }
        };

        $stack = new MiddlewareStack([$middleware]);

        $request = new Request('GET', 'https://example.com');
        $response = $stack->handle($request, fn () => new Response(200));

        expect($response->getHeaderLine('X-Modified'))->toBe('true');
    });
});

describe('LoggingMiddleware', function () {
    it('logs requests and responses', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/\[REQUEST\] GET https:\/\/example\.com/'), Mockery::type('array'));
        $logger->shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/\[RESPONSE\] 200 https:\/\/example\.com/'), Mockery::type('array'));

        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');
        $middleware->handle($request, fn () => new Response(200));
    });

    it('logs errors', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once(); // Request log
        $logger->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/\[ERROR\] GET https:\/\/example\.com/'), Mockery::type('array'));

        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');

        try {
            $middleware->handle($request, function () {
                throw new \RuntimeException('Network error');
            });
        } catch (\RuntimeException $e) {
            expect($e->getMessage())->toBe('Network error');
        }
    });
});

describe('TimeoutMiddleware', function () {
    it('adds timeout header to request', function () {
        $middleware = new TimeoutMiddleware(60);

        $request = new Request('GET', 'https://example.com');
        $middleware->handle($request, function ($req) {
            expect($req->getHeaderLine('X-Timeout'))->toBe('60');

            return new Response(200);
        });
    });

    it('can get configured timeout', function () {
        $middleware = new TimeoutMiddleware(45);

        expect($middleware->getTimeout())->toBe(45);
    });
});

afterEach(function () {
    Mockery::close();
});
