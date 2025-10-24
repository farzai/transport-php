# Transport PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/farzai/transport.svg?style=flat-square)](https://packagist.org/packages/farzai/transport)
[![Tests](https://img.shields.io/github/actions/workflow/status/farzai/transport-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/farzai/transport-php/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/farzai/transport-php/branch/main/graph/badge.svg)](https://codecov.io/gh/farzai/transport-php)
[![Total Downloads](https://img.shields.io/packagist/dt/farzai/transport.svg?style=flat-square)](https://packagist.org/packages/farzai/transport)

A modern, PSR-compliant HTTP client for PHP with middleware architecture, advanced retry strategies, and fluent API for building requests.

## Features

- ✅ **PSR-7** HTTP Message Interface
- ✅ **PSR-18** HTTP Client Interface
- ✅ **PSR-3** Logger Interface support
- ✅ **Middleware Architecture** - Extensible plugin system for custom behavior
- ✅ **Advanced Retry Strategies** - Exponential backoff with jitter, custom retry conditions
- ✅ **Fluent Request Builder** - Chainable API for building requests
- ✅ **Immutable Configuration** - Thread-safe, predictable behavior
- ✅ **Type-Safe** - Full PHP 8.1+ type hints and strict types
- ✅ **JSON Helpers** - Parse JSON with proper error handling
- ✅ **Custom Exceptions** - Detailed error context and retry information

## Requirements

- PHP 8.1 or higher

## Installation

You can install the package via composer:

```bash
composer require farzai/transport
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
        condition: RetryCondition::default()
            ->onExceptions([NetworkException::class])
    )
    ->build();

// Retries automatically with exponential backoff + jitter
$response = $transport->get('/unreliable-endpoint')->send();
```

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

### Error Handling

```php
use Farzai\Transport\Exceptions\RetryExhaustedException;
use Farzai\Transport\Exceptions\JsonParseException;
use Farzai\Transport\Exceptions\NetworkException;

// Throw exception on non-2xx responses
try {
    $response->throw();
} catch (\GuzzleHttp\Exception\BadResponseException $e) {
    echo $e->getMessage();
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

## Architecture

### Middleware System

The library uses a middleware pipeline architecture that allows you to easily extend functionality:

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

// ❌ No longer available - no mutable setters
// $transport->setTimeout(30);  // Method doesn't exist
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

Please see [CONTRIBUTING](https://github.com/farzai/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [parsilver](https://github.com/parsilver)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
