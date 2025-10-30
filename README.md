# Transport PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/farzai/transport.svg?style=flat-square)](https://packagist.org/packages/farzai/transport)
[![Tests](https://img.shields.io/github/actions/workflow/status/farzai/transport-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/farzai/transport-php/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/farzai/transport-php/branch/main/graph/badge.svg)](https://codecov.io/gh/farzai/transport-php)
[![Total Downloads](https://img.shields.io/packagist/dt/farzai/transport.svg?style=flat-square)](https://packagist.org/packages/farzai/transport)

A modern, PSR-compliant HTTP client for PHP with middleware architecture, advanced retry strategies, and fluent API for building requests.

## Features

- ✅ **PSR Standards** - Built on PSR-7 (HTTP Messages), PSR-17 (HTTP Factories), PSR-18 (HTTP Client)
- ✅ **No Hard Dependencies** - Use any PSR-18 HTTP client (Guzzle, Symfony, or custom)
- ✅ **Auto-Detection** - Automatically discovers and uses available HTTP clients
- ✅ **Middleware Architecture** - Extensible plugin system for custom behavior
- ✅ **Advanced Retry Strategies** - Exponential backoff with jitter, custom retry conditions
- ✅ **Fluent Request Builder** - Chainable API for building requests
- ✅ **Immutable Configuration** - Thread-safe, predictable behavior
- ✅ **Type-Safe** - Full PHP 8.1+ type hints and strict types
- ✅ **JSON Helpers** - Parse JSON with proper error handling and dot-notation access
- ✅ **Custom Exceptions** - Detailed error context implementing PSR standards
- ✅ **Easy to Swap HTTP Clients** - Switch between Guzzle, Symfony, or any PSR-18 client
- ✅ **File Upload & Multipart** - RFC 7578 compliant multipart/form-data with file upload support
- ✅ **Cookie Management** - RFC 6265 compliant automatic cookie handling with session support

## Requirements

- PHP 8.1 or higher

## Installation

You can install the package via composer:

```bash
composer require farzai/transport
```

The library will auto-detect any available PSR-18 HTTP client. If you don't have one installed, we recommend:

```bash
# Recommended: Modern HTTP client with async support
composer require symfony/http-client

# Alternative: Popular and widely-used
composer require guzzlehttp/guzzle
```

## Quick Start

Transport PHP automatically detects available HTTP clients (Symfony, Guzzle, etc.) - no configuration needed!

```php
use Farzai\Transport\TransportBuilder;

// Just works! Auto-detects your HTTP client
$transport = TransportBuilder::make()
    ->withBaseUri('https://api.example.com')
    ->build();

$response = $transport->get('/users')->send();
echo $response->json('data.0.name'); // Dot notation support!
```

## Usage

### Basic Usage (Fluent API)

```php
use Farzai\Transport\TransportBuilder;

// Create a transport client with configuration
$transport = TransportBuilder::make()
    ->withBaseUri('https://api.example.com')
    ->withHeaders([
        'Authorization' => 'Bearer token123',
        'Accept' => 'application/json',
    ])
    ->withTimeout(30)
    ->build();

// Make requests using fluent API
$response = $transport->get('/users/123')->send();

// Access response data
echo $response->statusCode(); // 200
echo $response->body(); // Raw response body
$data = $response->json(); // Parsed JSON as array
```

### Fluent Request Building

```php
// GET request with query parameters
$response = $transport
    ->get('/users')
    ->withQuery(['page' => 1, 'limit' => 10])
    ->withHeader('X-Custom-Header', 'value')
    ->send();

// POST with JSON body
$response = $transport
    ->post('/users')
    ->withJson([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ])
    ->send();

// POST with form data
$response = $transport
    ->post('/login')
    ->withForm([
        'username' => 'john',
        'password' => 'secret'
    ])
    ->send();

// With authentication
$response = $transport
    ->get('/protected')
    ->withBearerToken('your-token')
    ->send();

// Or basic auth
$response = $transport
    ->get('/protected')
    ->withBasicAuth('username', 'password')
    ->send();
```

### Working with JSON Responses

```php
// Parse JSON with automatic error handling
$data = $response->json(); // ['id' => 123, 'name' => 'John Doe']

// Get specific field using dot notation
$name = $response->json('name'); // 'John Doe'
$city = $response->json('user.address.city'); // 'New York'

// Get as array
$array = $response->toArray();

// Safe JSON parsing (returns null on error instead of throwing)
$data = $response->jsonOrNull();

// Check if response is successful
if ($response->isSuccessful()) {
    // Handle success (2xx status codes)
}
```

### Advanced Retry Logic

```php
use Farzai\Transport\Retry\ExponentialBackoffStrategy;
use Farzai\Transport\Retry\RetryCondition;
use Farzai\Transport\Exceptions\NetworkException;

// Configure retry with exponential backoff
$transport = TransportBuilder::make()
    ->withRetries(
        maxRetries: 3,
        strategy: new ExponentialBackoffStrategy(
            baseDelayMs: 1000,      // Start with 1 second
            multiplier: 2.0,         // Double each retry
            maxDelayMs: 30000,       // Cap at 30 seconds
            useJitter: true          // Add randomization
        ),
        condition: (new RetryCondition())
            ->onExceptions([NetworkException::class])
    )
    ->build();

// Retries automatically with exponential backoff + jitter
$response = $transport->get('/unreliable-endpoint')->send();

// Or use default retry condition (retries on any exception)
$transport = TransportBuilder::make()
    ->withRetries(
        maxRetries: 3,
        condition: RetryCondition::default() // Retries on ANY exception
    )
    ->build();
```

**Retry Conditions:**
- `RetryCondition::default()` - Retries on any exception
- `(new RetryCondition())->onExceptions([...])` - Retry only specific exceptions
- `(new RetryCondition())->onStatusCodes([500, 502, 503])` - Retry specific HTTP status codes

### Custom Middleware

```php
use Farzai\Transport\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Create custom middleware
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add auth token to all requests
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->getToken());

        return $next($request);
    }

    private function getToken(): string
    {
        // Your token logic
        return 'your-token';
    }
}

// Add to transport
$transport = TransportBuilder::make()
    ->withMiddleware(new AuthMiddleware())
    ->build();
```

### Configuration Options

```php
$transport = TransportBuilder::make()
    ->withBaseUri('https://api.example.com')
    ->withHeaders(['Accept' => 'application/json'])
    ->withTimeout(30)  // Seconds
    ->withRetries(3)   // Max retry attempts
    ->withMiddleware($customMiddleware)
    ->withoutDefaultMiddlewares()  // Disable logging, timeout, retry middlewares
    ->setClient($customPsrClient)  // Use custom PSR-18 client
    ->setLogger($customLogger)     // Use custom PSR-3 logger
    ->build();
```

### HTTP Client Selection

```php
use Farzai\Transport\Factory\ClientFactory;

// Auto-detect (recommended - uses Symfony > Guzzle > Others)
$transport = TransportBuilder::make()->build();

// Explicitly use Guzzle
$transport = TransportBuilder::make()
    ->setClient(ClientFactory::createGuzzle(['timeout' => 30]))
    ->build();

// Explicitly use Symfony HTTP Client
$transport = TransportBuilder::make()
    ->setClient(ClientFactory::createSymfony(['max_redirects' => 5]))
    ->build();

// Check which client is being used
echo ClientFactory::getDetectedClientName(); // e.g., "Symfony\Component\HttpClient\Psr18Client"
```

### File Upload & Multipart Requests

```php
// Simple file upload
$response = $transport->post('/upload')
    ->withFile(
        name: 'document',
        path: '/path/to/file.pdf',
        filename: 'report.pdf',
        additionalFields: ['title' => 'Monthly Report']
    )
    ->send();

// Multiple files with form data
$response = $transport->post('/upload')
    ->withMultipart([
        // Text fields
        ['name' => 'title', 'contents' => 'My Upload'],
        ['name' => 'description', 'contents' => 'File description'],

        // File uploads
        [
            'name' => 'avatar',
            'contents' => file_get_contents('photo.jpg'),
            'filename' => 'avatar.jpg',
            'content-type' => 'image/jpeg'
        ],
        [
            'name' => 'document',
            'contents' => fopen('/path/to/file.pdf', 'r'),
            'filename' => 'document.pdf'
        ]
    ])
    ->send();

// Advanced: Using MultipartStreamBuilder
use Farzai\Transport\Multipart\MultipartStreamBuilder;

$builder = new MultipartStreamBuilder();
$builder->addField('username', 'john_doe')
    ->addFile('avatar', '/path/to/avatar.jpg', 'profile.jpg')
    ->addFileContents('data', $jsonData, 'data.json', 'application/json');

$response = $transport->post('/api/upload')
    ->withMultipartBuilder($builder)
    ->send();

// Memory-efficient streaming for large files
use Farzai\Transport\Multipart\StreamingMultipartBuilder;

$streamBuilder = new StreamingMultipartBuilder();
$stream = $streamBuilder
    ->addFile('video', '/path/to/large-video.mp4', 'video.mp4')
    ->addField('title', 'My Video')
    ->build();

// Streams file without loading entire content into memory
$response = $transport->request()
    ->withBody($stream)
    ->withHeader('Content-Type', $streamBuilder->getContentType())
    ->post('/upload')
    ->send();
```

**Note:** The library automatically selects `StreamingMultipartBuilder` for large files (>1MB by default) to optimize memory usage. You can also manually use it for memory-efficient uploads of any size.

### Cookie Management

```php
// Automatic cookie handling
$transport = TransportBuilder::make()
    ->withBaseUri('https://api.example.com')
    ->withCookies() // Enable automatic cookie management
    ->build();

// Login - cookies are automatically stored
$transport->post('/login')
    ->withJson(['username' => 'user', 'password' => 'pass'])
    ->send();

// Subsequent requests automatically include cookies
$response = $transport->get('/profile')->send();

// Advanced: Manual cookie management
use Farzai\Transport\Cookie\CookieJar;
use Farzai\Transport\Cookie\Cookie;

$cookieJar = new CookieJar();

// Add cookies manually
$cookieJar->setCookie(new Cookie(
    name: 'session_id',
    value: 'abc123',
    expiresAt: time() + 3600,
    domain: 'example.com',
    path: '/',
    secure: true,
    httpOnly: true
));

$transport = TransportBuilder::make()
    ->withCookieJar($cookieJar)
    ->build();

// Inspect cookies
echo "Cookies: {$cookieJar->count()}\n";
foreach ($cookieJar->getAllCookies() as $cookie) {
    echo "{$cookie->getName()}: {$cookie->getValue()}\n";
}

// Export/Import cookies for persistence
$data = $cookieJar->toArray();
file_put_contents('cookies.json', json_encode($data));

// Later...
$newJar = new CookieJar();
$newJar->fromArray(json_decode(file_get_contents('cookies.json'), true));
```

**Performance Note:** The cookie management system automatically optimizes for different workloads. For applications with many cookies (50+), it uses indexed collections with O(1) domain lookups. For smaller cookie counts, it uses simpler collections to minimize overhead.

### Event Monitoring

Monitor HTTP requests lifecycle with event listeners:

```php
use Farzai\Transport\Events\RequestSendingEvent;
use Farzai\Transport\Events\ResponseReceivedEvent;
use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Events\RetryAttemptEvent;

$transport = TransportBuilder::make()
    ->withBaseUri('https://api.example.com')
    // Track successful responses
    ->addEventListener(ResponseReceivedEvent::class, function ($event) {
        printf(
            "[SUCCESS] %s %s → %d (%.2fms)\n",
            $event->getMethod(),
            $event->getUri(),
            $event->getStatusCode(),
            $event->getDuration()
        );
    })
    // Track failed requests
    ->addEventListener(RequestFailedEvent::class, function ($event) {
        printf(
            "[ERROR] %s %s failed: %s\n",
            $event->getMethod(),
            $event->getUri(),
            $event->getExceptionMessage()
        );
    })
    // Monitor retry attempts
    ->addEventListener(RetryAttemptEvent::class, function ($event) {
        printf(
            "[RETRY] Attempt %d/%d (delay: %dms)\n",
            $event->getAttemptNumber(),
            $event->getMaxAttempts(),
            $event->getDelay()
        );
    })
    ->withRetries(3)
    ->build();

// Events are automatically dispatched during request lifecycle
$response = $transport->get('/api/endpoint')->send();
```

**Available Events:**
- `RequestSendingEvent` - Before a request is sent
- `ResponseReceivedEvent` - After successful response (includes duration metrics)
- `RequestFailedEvent` - When a request fails with exception details
- `RetryAttemptEvent` - Before each retry attempt with delay information

**Use Cases:**
- Performance monitoring and metrics collection
- Logging and debugging request/response cycles
- Custom retry notifications
- Request/response instrumentation

### Error Handling

```php
use Farzai\Transport\Exceptions\ClientException;
use Farzai\Transport\Exceptions\ServerException;
use Farzai\Transport\Exceptions\RetryExhaustedException;
use Farzai\Transport\Exceptions\JsonParseException;

// Throw exception on non-2xx responses
try {
    $response->throw();
} catch (ClientException $e) {
    // 4xx errors
    echo "Client error: {$e->getStatusCode()}\n";
    echo "Request: {$e->getRequest()->getUri()}\n";
    var_dump($e->getContext()); // Rich debugging context
} catch (ServerException $e) {
    // 5xx errors
    echo "Server error: {$e->getStatusCode()}\n";
}

// Custom error handling callback
$response->throw(function ($response, $exception) {
    if ($response->statusCode() === 404) {
        throw new \Exception('Resource not found!');
    }
    throw $exception;
});

// Handle retry exhaustion
try {
    $response = $transport->get('/flaky-endpoint')->send();
} catch (RetryExhaustedException $e) {
    echo "Failed after {$e->getAttempts()} attempts\n";
    echo "Delays used: " . implode(', ', $e->getDelaysUsed()) . "ms\n";

    foreach ($e->getRetryExceptions() as $attempt => $exception) {
        echo "Attempt $attempt: {$exception->getMessage()}\n";
    }
}

// Handle JSON parse errors
try {
    $data = $response->json();
} catch (JsonParseException $e) {
    echo "Invalid JSON: {$e->getMessage()}\n";
    echo "JSON string: {$e->jsonString}\n";
    echo "Error code: {$e->jsonErrorCode}\n";
}
```

### Using Custom PSR-18 Client and Logger

```php
use Farzai\Transport\TransportBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create custom logger
$logger = new Logger('http-client');
$logger->pushHandler(new StreamHandler('path/to/your.log'));

// Use any PSR-18 compliant client
$client = new \Your\Custom\Psr18Client();

$transport = TransportBuilder::make()
    ->setClient($client)
    ->setLogger($logger)
    ->build();
```

### Testing with Response Builder

```php
use Farzai\Transport\ResponseBuilder;

$response = ResponseBuilder::create()
    ->statusCode(200)
    ->withHeader('Content-Type', 'application/json')
    ->withBody('{"success": true}')
    ->build();

// Or use the fluent builder methods
$response = ResponseBuilder::create()
    ->statusCode(404)
    ->withHeaders([
        'Content-Type' => 'application/json',
        'X-Request-ID' => '12345'
    ])
    ->withBody('{"error": "Not found"}')
    ->withVersion('1.1')
    ->withReason('Not Found')
    ->build();
```

## Documentation

- **[Examples](examples/)** - Practical usage examples:
  - [Basic Usage](examples/basic-usage.php)
  - [Custom HTTP Clients](examples/custom-client.php)
  - [Advanced Retry Logic](examples/advanced-retry.php)
  - [Custom Middleware](examples/middleware-example.php)
  - [File Upload](examples/file-upload.php)
  - [Streaming Upload](examples/streaming-upload.php)
  - [Cookie Session Management](examples/cookie-session.php)
  - [Event Monitoring](examples/event-monitoring.php)

## Architecture

### No Hard Dependencies on Guzzle

Transport PHP v2.x uses PSR standards and auto-detection:

- **PSR-7** - HTTP Message Interface
- **PSR-17** - HTTP Factories for creating requests/responses
- **PSR-18** - HTTP Client Interface

This means you can use **any** PSR-18 compliant HTTP client:
- ✅ Symfony HTTP Client (modern, async, HTTP/2)
- ✅ Guzzle (popular, stable)
- ✅ Any custom PSR-18 implementation

### Middleware System

```
Request → Middleware Stack → HTTP Client → Response
          ↓
     [LoggingMiddleware]
     [TimeoutMiddleware]
     [RetryMiddleware]
     [CustomMiddleware...]
```

Default middlewares (can be disabled with `withoutDefaultMiddlewares()`):
- **LoggingMiddleware**: Logs requests and responses
- **TimeoutMiddleware**: Enforces request timeouts
- **RetryMiddleware**: Handles retry logic with configurable strategies

### Immutable Configuration

All configuration is immutable and set during the build phase:

```php
// ✅ Correct - configuration during build
$transport = TransportBuilder::make()
    ->withTimeout(30)
    ->withRetries(3)
    ->build();
```

This makes the Transport instance:
- **Thread-safe** - Can be safely shared across threads
- **Predictable** - Configuration can't change unexpectedly
- **Easier to test** - No hidden state changes

## Testing

```bash
composer test
```

## Code Quality

```bash
# Run tests with coverage
composer test-coverage

# Fix code style
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](docs/contributing/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [parsilver](https://github.com/parsilver)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
