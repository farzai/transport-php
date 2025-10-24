<?php

declare(strict_types=1);

use Farzai\Transport\Transport;
use Farzai\Transport\TransportBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

describe('TransportBuilder', function () {
    it('can build transport with default values', function () {
        $transport = TransportBuilder::make()->build();

        expect($transport)->toBeInstanceOf(Transport::class)
            ->and($transport->getPsrClient())->toBeInstanceOf(\Psr\Http\Client\ClientInterface::class) // Auto-detected client
            ->and($transport->getLogger())->toBeInstanceOf(NullLogger::class)
            ->and($transport->getUri())->toBe('')
            ->and($transport->getTimeout())->toBe(30)
            ->and($transport->getRetries())->toBe(0);
    });

    it('can build transport with custom client and logger', function () {
        $client = new GuzzleClient;
        $logger = new NullLogger;

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->setLogger($logger)
            ->build();

        expect($transport->getPsrClient())->toBe($client)
            ->and($transport->getLogger())->toBe($logger);
    });

    it('can configure base URI', function () {
        $transport = TransportBuilder::make()
            ->withBaseUri('https://api.example.com')
            ->build();

        expect($transport->getUri())->toBe('https://api.example.com');
    });

    it('can configure headers', function () {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ];

        $transport = TransportBuilder::make()
            ->withHeaders($headers)
            ->build();

        expect($transport->getHeaders())->toBe($headers);
    });

    it('can configure timeout', function () {
        $transport = TransportBuilder::make()
            ->withTimeout(60)
            ->build();

        expect($transport->getTimeout())->toBe(60);
    });

    it('can configure retries', function () {
        $transport = TransportBuilder::make()
            ->withRetries(5)
            ->build();

        expect($transport->getRetries())->toBe(5);
    });

    it('builder is immutable', function () {
        $builder = TransportBuilder::make();
        $builder2 = $builder->withTimeout(60);

        expect($builder)->not->toBe($builder2);
    });

    it('can get configured client', function () {
        $client = new GuzzleClient;
        $builder = TransportBuilder::make()->setClient($client);

        expect($builder->getClient())->toBe($client);
    });

    it('can get configured logger', function () {
        $logger = new NullLogger;
        $builder = TransportBuilder::make()->setLogger($logger);

        expect($builder->getLogger())->toBe($logger);
    });

    it('returns null when no client configured', function () {
        $builder = TransportBuilder::make();

        expect($builder->getClient())->toBeNull();
    });

    it('returns null when no logger configured', function () {
        $builder = TransportBuilder::make();

        expect($builder->getLogger())->toBeNull();
    });

    it('can add custom middleware', function () {
        $middleware = Mockery::mock(\Farzai\Transport\Middleware\MiddlewareInterface::class);
        $middleware->shouldReceive('handle')
            ->once()
            ->andReturnUsing(function ($request, $next) {
                return $next($request);
            });

        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new GuzzleResponse(200, [], '{}'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withMiddleware($middleware)
            ->withoutDefaultMiddlewares()
            ->build();

        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');
        $transport->sendRequest($request);
    });
});

describe('Transport', function () {
    it('can send PSR request and get PSR response', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new GuzzleResponse(200, [], 'Hello World'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/api/test');
        $response = $transport->sendRequest($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getBody()->getContents())->toBe('Hello World');
    });

    it('prepends base URI to relative requests', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(function ($request) {
                return (string) $request->getUri() === 'https://api.example.com/users/123';
            }))
            ->andReturn(new GuzzleResponse(200));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withBaseUri('https://api.example.com')
            ->withoutDefaultMiddlewares()
            ->build();

        $request = new \GuzzleHttp\Psr7\Request('GET', '/users/123');
        $transport->sendRequest($request);
    });

    it('adds default headers to requests', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request->getHeaderLine('X-Custom') === 'value'
                    && $request->getHeaderLine('Accept') === 'application/json';
            }))
            ->andReturn(new GuzzleResponse(200));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withHeaders([
                'X-Custom' => 'value',
                'Accept' => 'application/json',
            ])
            ->withoutDefaultMiddlewares()
            ->build();

        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');
        $transport->sendRequest($request);
    });

    it('does not override request headers with default headers', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request->getHeaderLine('Content-Type') === 'text/plain';
            }))
            ->andReturn(new GuzzleResponse(200));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withoutDefaultMiddlewares()
            ->build();

        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            'https://example.com',
            ['Content-Type' => 'text/plain']
        );
        $transport->sendRequest($request);
    });

    it('can get transport config', function () {
        $transport = TransportBuilder::make()
            ->withBaseUri('https://api.example.com')
            ->withTimeout(60)
            ->withRetries(3)
            ->build();

        $config = $transport->getConfig();

        expect($config)->toBeInstanceOf(\Farzai\Transport\TransportConfig::class)
            ->and($config->baseUri)->toBe('https://api.example.com')
            ->and($config->timeout)->toBe(60)
            ->and($config->maxRetries)->toBe(3);
    });

    it('send method returns ResponseInterface when PSR response is already ResponseInterface', function () {
        $client = Mockery::mock(ClientInterface::class);
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');

        // Create a Response that implements both PSR ResponseInterface and our ResponseInterface
        $mockResponse = Mockery::mock(\Farzai\Transport\Contracts\ResponseInterface::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getReasonPhrase')->andReturn('OK');
        $mockResponse->shouldReceive('getProtocolVersion')->andReturn('1.1');
        $mockResponse->shouldReceive('getHeaders')->andReturn([]);
        $mockResponse->shouldReceive('hasHeader')->andReturn(false);
        $mockResponse->shouldReceive('getHeader')->andReturn([]);
        $mockResponse->shouldReceive('getHeaderLine')->andReturn('');
        $mockResponse->shouldReceive('getBody')->andReturn(Mockery::mock(\Psr\Http\Message\StreamInterface::class));

        $client->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockResponse);

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport->send($request);

        expect($response)->toBe($mockResponse);
    });
});

describe('Transport fluent API', function () {
    it('can create GET request', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn ($req) => $req->getMethod() === 'GET'))
            ->andReturn(new GuzzleResponse(200, [], '{"success":true}'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport->get('/users')->send();

        expect($response->isSuccessful())->toBeTrue();
    });

    it('can create POST request', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn ($req) => $req->getMethod() === 'POST'))
            ->andReturn(new GuzzleResponse(201, [], '{"id":123}'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport->post('/users')->send();

        expect($response->statusCode())->toBe(201);
    });

    it('can create PUT request', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn ($req) => $req->getMethod() === 'PUT'))
            ->andReturn(new GuzzleResponse(200));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $transport->put('/users/123')->send();
        expect(true)->toBeTrue();
    });

    it('can create PATCH request', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn ($req) => $req->getMethod() === 'PATCH'))
            ->andReturn(new GuzzleResponse(200));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $transport->patch('/users/123')->send();
        expect(true)->toBeTrue();
    });

    it('can create DELETE request', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(fn ($req) => $req->getMethod() === 'DELETE'))
            ->andReturn(new GuzzleResponse(204));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport->delete('/users/123')->send();
        expect($response->statusCode())->toBe(204);
    });
});

afterEach(function () {
    Mockery::close();
});
