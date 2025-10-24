<?php

declare(strict_types=1);

use Farzai\Transport\RequestBuilder;
use Farzai\Transport\TransportBuilder;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Psr\Http\Client\ClientInterface;

describe('RequestBuilder static methods', function () {
    it('can create GET request', function () {
        $request = RequestBuilder::get('https://api.example.com/users');

        expect($request)->toBeInstanceOf(RequestBuilder::class)
            ->and($request->build()->getMethod())->toBe('GET')
            ->and((string) $request->build()->getUri())->toBe('https://api.example.com/users');
    });

    it('can create POST request', function () {
        $request = RequestBuilder::post('/users');

        expect($request->build()->getMethod())->toBe('POST');
    });

    it('can create PUT request', function () {
        $request = RequestBuilder::put('/users/123');

        expect($request->build()->getMethod())->toBe('PUT');
    });

    it('can create PATCH request', function () {
        $request = RequestBuilder::patch('/users/123');

        expect($request->build()->getMethod())->toBe('PATCH');
    });

    it('can create DELETE request', function () {
        $request = RequestBuilder::delete('/users/123');

        expect($request->build()->getMethod())->toBe('DELETE');
    });

    it('can create HEAD request', function () {
        $request = RequestBuilder::head('/users');

        expect($request->build()->getMethod())->toBe('HEAD');
    });

    it('can create OPTIONS request', function () {
        $request = RequestBuilder::options('/users');

        expect($request->build()->getMethod())->toBe('OPTIONS');
    });
});

describe('RequestBuilder fluent API', function () {
    it('can set headers', function () {
        $request = RequestBuilder::get('/users')
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Custom', 'value')
            ->build();

        expect($request->getHeaderLine('Accept'))->toBe('application/json')
            ->and($request->getHeaderLine('X-Custom'))->toBe('value');
    });

    it('can set multiple headers at once', function () {
        $request = RequestBuilder::get('/users')
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Custom' => 'value',
            ])
            ->build();

        expect($request->getHeaderLine('Accept'))->toBe('application/json')
            ->and($request->getHeaderLine('X-Custom'))->toBe('value');
    });

    it('can set JSON body', function () {
        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $request = RequestBuilder::post('/users')
            ->withJson($data)
            ->build();

        expect($request->getBody()->getContents())->toBe(json_encode($data))
            ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');
    });

    it('can set form data body', function () {
        $data = ['username' => 'john', 'password' => 'secret'];

        $request = RequestBuilder::post('/login')
            ->withForm($data)
            ->build();

        expect($request->getBody()->getContents())->toBe(http_build_query($data))
            ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded');
    });

    it('can set query parameters', function () {
        $request = RequestBuilder::get('/users')
            ->withQuery(['page' => 1, 'limit' => 10])
            ->build();

        expect($request->getUri()->getQuery())->toBe('page=1&limit=10');
    });

    it('can merge query parameters', function () {
        $request = RequestBuilder::get('/users?sort=name')
            ->withQuery(['page' => 1])
            ->withQuery(['limit' => 10])
            ->build();

        $query = $request->getUri()->getQuery();
        expect($query)->toContain('sort=name')
            ->and($query)->toContain('page=1')
            ->and($query)->toContain('limit=10');
    });

    it('can set basic authentication', function () {
        $request = RequestBuilder::get('/protected')
            ->withBasicAuth('user', 'pass')
            ->build();

        $expected = 'Basic '.base64_encode('user:pass');
        expect($request->getHeaderLine('Authorization'))->toBe($expected);
    });

    it('can set bearer token', function () {
        $request = RequestBuilder::get('/protected')
            ->withBearerToken('my-token-123')
            ->build();

        expect($request->getHeaderLine('Authorization'))->toBe('Bearer my-token-123');
    });

    it('is immutable', function () {
        $builder = RequestBuilder::get('/users');
        $builder2 = $builder->withHeader('Accept', 'application/json');

        expect($builder)->not->toBe($builder2);
    });

    it('can set body with StreamInterface', function () {
        $stream = \Farzai\Transport\Factory\HttpFactory::getInstance()
            ->createStream('test body content');

        $request = RequestBuilder::post('/users')
            ->withBody($stream)
            ->build();

        expect($request->getBody()->getContents())->toBe('test body content');
    });

    it('throws exception when sending without transport', function () {
        $builder = RequestBuilder::get('/users');

        expect(fn () => $builder->send())
            ->toThrow(RuntimeException::class, 'No transport instance available');
    });
});

describe('RequestBuilder with Transport', function () {
    it('can send request through transport', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new GuzzleResponse(200, [], '{"success":true}'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport
            ->get('/users')
            ->withHeader('Accept', 'application/json')
            ->send();

        expect($response->isSuccessful())->toBeTrue()
            ->and($response->json())->toBe(['success' => true]);
    });

    it('can chain multiple operations', function () {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request->getMethod() === 'POST'
                    && $request->getHeaderLine('Content-Type') === 'application/json'
                    && $request->getHeaderLine('Accept') === 'application/json'
                    && str_contains($request->getUri()->getQuery(), 'format=json');
            }))
            ->andReturn(new GuzzleResponse(201, [], '{"id":123}'));

        $transport = TransportBuilder::make()
            ->setClient($client)
            ->withoutDefaultMiddlewares()
            ->build();

        $response = $transport
            ->post('/users')
            ->withJson(['name' => 'John'])
            ->withHeader('Accept', 'application/json')
            ->withQuery(['format' => 'json'])
            ->send();

        expect($response->statusCode())->toBe(201);
    });
});

afterEach(function () {
    Mockery::close();
});
