<?php

/**
 * Custom Middleware Example
 *
 * This example demonstrates how to create and use custom middleware
 * with Transport PHP to extend functionality.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Middleware\MiddlewareInterface;
use Farzai\Transport\TransportBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

echo "=== Custom Middleware Examples ===\n\n";

// Example 1: Simple Header Middleware
echo "1. Custom Header Middleware\n";
echo str_repeat('-', 50)."\n";

class CustomHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $headerName,
        private readonly string $headerValue
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add custom header to request
        $request = $request->withHeader($this->headerName, $this->headerValue);

        return $next($request);
    }
}

$transport = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withMiddleware(new CustomHeaderMiddleware('X-Custom-Header', 'my-value'))
    ->build();

try {
    $response = $transport->get('/posts/1')->send();
    echo "✓ Request completed with custom header\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 2: Request/Response Logging Middleware
echo "2. Request/Response Logging Middleware\n";
echo str_repeat('-', 50)."\n";

class DetailedLoggingMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $startTime = microtime(true);

        echo "→ Request:\n";
        echo "  Method: {$request->getMethod()}\n";
        echo "  URI: {$request->getUri()}\n";
        echo "  Headers:\n";
        foreach ($request->getHeaders() as $name => $values) {
            echo "    {$name}: ".implode(', ', $values)."\n";
        }

        // Execute request
        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        echo "\n← Response:\n";
        echo "  Status: {$response->getStatusCode()}\n";
        echo "  Duration: {$duration}ms\n";
        echo "  Headers:\n";
        foreach ($response->getHeaders() as $name => $values) {
            echo "    {$name}: ".implode(', ', $values)."\n";
        }
        echo "\n";

        return $response;
    }
}

$transport2 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withoutDefaultMiddlewares() // Disable default logging
    ->withMiddleware(new DetailedLoggingMiddleware)
    ->build();

try {
    $response = $transport2->get('/posts/1')->send();
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 3: Authentication Middleware
echo "3. API Key Authentication Middleware\n";
echo str_repeat('-', 50)."\n";

class ApiKeyAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $headerName = 'X-API-Key'
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add API key to all requests
        $request = $request->withHeader($this->headerName, $this->apiKey);

        return $next($request);
    }
}

$transport3 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withMiddleware(new ApiKeyAuthMiddleware('your-api-key-here'))
    ->build();

try {
    $response = $transport3->get('/posts/1')->send();
    echo "✓ Request completed with API key authentication\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 4: Response Caching Middleware
echo "4. Simple Response Caching Middleware\n";
echo str_repeat('-', 50)."\n";

class SimpleCacheMiddleware implements MiddlewareInterface
{
    private array $cache = [];

    public function __construct(
        private readonly int $ttlSeconds = 60
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return $next($request);
        }

        $cacheKey = (string) $request->getUri();

        // Check cache
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];

            if (time() - $cached['time'] < $this->ttlSeconds) {
                echo "  ✓ Cache HIT for: {$cacheKey}\n\n";

                return $cached['response'];
            }

            unset($this->cache[$cacheKey]);
        }

        echo "  ✗ Cache MISS for: {$cacheKey}\n";
        $response = $next($request);

        // Store in cache
        $this->cache[$cacheKey] = [
            'response' => $response,
            'time' => time(),
        ];
        echo "  ✓ Cached response\n\n";

        return $response;
    }
}

$cacheMiddleware = new SimpleCacheMiddleware(ttlSeconds: 60);

$transport4 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withMiddleware($cacheMiddleware)
    ->build();

try {
    echo "First request (should MISS cache):\n";
    $response1 = $transport4->get('/posts/1')->send();

    echo "Second request (should HIT cache):\n";
    $response2 = $transport4->get('/posts/1')->send();
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 5: Rate Limiting Middleware
echo "5. Simple Rate Limiting Middleware\n";
echo str_repeat('-', 50)."\n";

class RateLimitMiddleware implements MiddlewareInterface
{
    private array $requests = [];

    public function __construct(
        private readonly int $maxRequests = 10,
        private readonly int $perSeconds = 60
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $now = time();

        // Clean old requests
        $this->requests = array_filter(
            $this->requests,
            fn ($timestamp) => $now - $timestamp < $this->perSeconds
        );

        // Check rate limit
        if (count($this->requests) >= $this->maxRequests) {
            echo "  ⚠ Rate limit exceeded ({$this->maxRequests} requests per {$this->perSeconds}s)\n";
            echo "  Waiting...\n\n";

            // Wait until we can make the next request
            $oldestRequest = min($this->requests);
            $waitTime = $this->perSeconds - ($now - $oldestRequest) + 1;
            sleep($waitTime);

            // Clean again after waiting
            $now = time();
            $this->requests = array_filter(
                $this->requests,
                fn ($timestamp) => $now - $timestamp < $this->perSeconds
            );
        }

        // Record this request
        $this->requests[] = $now;

        echo '  ✓ Rate limit OK ('.count($this->requests)."/{$this->maxRequests} requests)\n\n";

        return $next($request);
    }
}

$transport5 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withMiddleware(new RateLimitMiddleware(maxRequests: 3, perSeconds: 10))
    ->build();

try {
    echo "Making 4 requests (rate limit: 3/10s):\n\n";

    for ($i = 1; $i <= 4; $i++) {
        echo "Request {$i}:\n";
        $response = $transport5->get('/posts/1')->send();
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 6: Chaining Multiple Middlewares
echo "6. Chaining Multiple Middlewares\n";
echo str_repeat('-', 50)."\n";

$transport6 = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withMiddleware(new ApiKeyAuthMiddleware('test-key'))
    ->withMiddleware(new CustomHeaderMiddleware('X-Request-ID', uniqid()))
    ->build();

try {
    $response = $transport6->get('/posts/1')->send();
    echo "✓ Request completed with multiple middlewares:\n";
    echo "  - API Key Authentication\n";
    echo "  - Custom Request ID Header\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

echo "=== Examples Complete ===\n";
