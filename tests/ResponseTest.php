<?php

declare(strict_types=1);

use Farzai\Transport\Exceptions\JsonParseException;
use Farzai\Transport\Response;
use Farzai\Transport\ResponseBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

describe('Response', function () {
    it('can get response status code', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(200);

        $response = new Response($request, $psrResponse);

        expect($response->statusCode())->toBe(200)
            ->and($response->isSuccessful())->toBeTrue();
    });

    it('can get response body', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"foo":"bar"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->body())->toBe('{"foo":"bar"}');
    });

    it('can get response headers', function () {
        $request = Mockery::mock(RequestInterface::class);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getHeaders')
            ->andReturn(['Content-Type' => ['application/json']]);

        $response = new Response($request, $psrResponse);

        expect($response->headers())->toBe([
            'Content-Type' => ['application/json'],
        ]);
    });

    it('identifies successful 2xx responses', function () {
        $request = Mockery::mock(RequestInterface::class);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(200);
        $response = new Response($request, $psrResponse);
        expect($response->isSuccessful())->toBeTrue();
    });

    it('identifies edge of successful range (299)', function () {
        $request = Mockery::mock(RequestInterface::class);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(299);
        $response = new Response($request, $psrResponse);
        expect($response->isSuccessful())->toBeTrue();
    });

    it('identifies 4xx as unsuccessful', function () {
        $request = Mockery::mock(RequestInterface::class);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(400);
        $response = new Response($request, $psrResponse);
        expect($response->isSuccessful())->toBeFalse();
    });

    it('identifies 5xx as unsuccessful', function () {
        $request = Mockery::mock(RequestInterface::class);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(500);
        $response = new Response($request, $psrResponse);
        expect($response->isSuccessful())->toBeFalse();
    });
});

describe('Response JSON parsing', function () {
    it('can parse valid JSON', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"foo":"bar"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->json())->toBe(['foo' => 'bar']);
    });

    it('can get nested JSON values using dot notation', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"user":{"name":"John","address":{"city":"NYC"}}}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->json('user.name'))->toBe('John')
            ->and($response->json('user.address.city'))->toBe('NYC');
    });

    it('throws exception on invalid JSON', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"foo":"bar"');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        $response->json();
    })->throws(JsonParseException::class);

    it('can safely parse JSON with jsonOrNull', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"foo":"bar"');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->jsonOrNull())->toBeNull();
    });

    it('returns null for empty body', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->json())->toBeNull();
    });

    it('can convert response to array', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"foo":"bar","items":[1,2,3]}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->toArray())->toBe([
            'foo' => 'bar',
            'items' => [1, 2, 3],
        ]);
    });

    it('caches parsed JSON', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn('{"foo":"bar"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->once()->andReturn($stream);

        $response = new Response($request, $psrResponse);

        // First call parses
        $response->json();
        // Second call should use cache
        $result = $response->json();

        expect($result)->toBe(['foo' => 'bar']);
    });
});

describe('Response error handling', function () {
    it('can throw exception on non-successful response', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"error":"Not found"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(404);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        $response->throw();
    })->throws(\GuzzleHttp\Exception\BadResponseException::class);

    it('does not throw on successful response', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(200);

        $response = new Response($request, $psrResponse);

        $result = $response->throw();

        expect($result)->toBe($response);
    });

    it('can use custom error callback', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"error":"Not found"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(404);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        $response->throw(function ($resp, $exception) {
            if ($resp->statusCode() === 404) {
                throw new \RuntimeException('Custom not found error');
            }
        });
    })->throws(\RuntimeException::class, 'Custom not found error');
});

describe('ResponseBuilder', function () {
    it('can build PSR response', function () {
        $response = ResponseBuilder::create()
            ->statusCode(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody('{"success":true}')
            ->build();

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getBody()->getContents())->toBe('{"success":true}')
            ->and($response->getHeaders())->toBe([
                'Content-Type' => ['application/json'],
            ]);
    });

    it('can build response with multiple headers', function () {
        $response = ResponseBuilder::create()
            ->statusCode(201)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Request-ID' => '12345',
            ])
            ->withBody('{"id":123}')
            ->build();

        expect($response->getStatusCode())->toBe(201)
            ->and($response->getHeaders())->toHaveKey('Content-Type')
            ->and($response->getHeaders())->toHaveKey('X-Request-ID');
    });

    it('can build response with version and reason', function () {
        $response = ResponseBuilder::create()
            ->statusCode(404)
            ->withVersion('1.1')
            ->withReason('Not Found')
            ->build();

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getProtocolVersion())->toBe('1.1')
            ->and($response->getReasonPhrase())->toBe('Not Found');
    });
});

afterEach(function () {
    Mockery::close();
});
