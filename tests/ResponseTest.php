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

    it('returns null when getting key from non-array JSON data', function () {
        $request = Mockery::mock(RequestInterface::class);

        // Use a serializer configured to return objects
        $config = \Farzai\Transport\Serialization\JsonConfig::default()->withAssociative(false);
        $serializer = new \Farzai\Transport\Serialization\JsonSerializer($config);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"name":"John"}');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse, $serializer);

        // When JSON is parsed as object and we try to get a key, it should return null
        $result = $response->json('name');

        expect($result)->toBeNull();
    });

    it('returns empty array when toArray is called on non-array JSON', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('"just a string"');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        $result = $response->toArray();

        expect($result)->toBe([]);
    });

    it('returns empty array when toArray is called on integer JSON', function () {
        $request = Mockery::mock(RequestInterface::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('123');

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        $result = $response->toArray();

        expect($result)->toBe([]);
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
    })->throws(\Farzai\Transport\Exceptions\ClientException::class);

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

    it('can build response with StreamInterface body', function () {
        $stream = \Farzai\Transport\Factory\HttpFactory::getInstance()
            ->createStream('stream body content');

        $response = ResponseBuilder::create()
            ->statusCode(200)
            ->withBody($stream)
            ->build();

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getBody()->getContents())->toBe('stream body content');
    });

    it('can build response with custom protocol version', function () {
        $response = ResponseBuilder::create()
            ->statusCode(200)
            ->withVersion('2.0')
            ->withBody('HTTP/2 response')
            ->build();

        expect($response->getProtocolVersion())->toBe('2.0')
            ->and($response->getBody()->getContents())->toBe('HTTP/2 response');
    });
});

describe('Response PSR-7 implementation', function () {
    it('implements getStatusCode', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getStatusCode')->andReturn(200);

        $response = new Response($request, $psrResponse);

        expect($response->getStatusCode())->toBe(200);
    });

    it('implements getReasonPhrase', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getReasonPhrase')->andReturn('OK');

        $response = new Response($request, $psrResponse);

        expect($response->getReasonPhrase())->toBe('OK');
    });

    it('implements getProtocolVersion', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getProtocolVersion')->andReturn('1.1');

        $response = new Response($request, $psrResponse);

        expect($response->getProtocolVersion())->toBe('1.1');
    });

    it('implements hasHeader', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);

        $response = new Response($request, $psrResponse);

        expect($response->hasHeader('Content-Type'))->toBeTrue();
    });

    it('implements getHeader', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getHeader')->with('Content-Type')->andReturn(['application/json']);

        $response = new Response($request, $psrResponse);

        expect($response->getHeader('Content-Type'))->toBe(['application/json']);
    });

    it('implements getHeaderLine', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/json');

        $response = new Response($request, $psrResponse);

        expect($response->getHeaderLine('Content-Type'))->toBe('application/json');
    });

    it('implements getBody', function () {
        $request = Mockery::mock(RequestInterface::class);
        $stream = Mockery::mock(StreamInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('getBody')->andReturn($stream);

        $response = new Response($request, $psrResponse);

        expect($response->getBody())->toBe($stream);
    });

    it('implements withProtocolVersion', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('withProtocolVersion')->with('2.0')->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withProtocolVersion('2.0');

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('implements withHeader', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('withHeader')->with('X-Custom', 'value')->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withHeader('X-Custom', 'value');

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('implements withAddedHeader', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('withAddedHeader')->with('Set-Cookie', 'value')->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withAddedHeader('Set-Cookie', 'value');

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('implements withoutHeader', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('withoutHeader')->with('X-Deprecated')->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withoutHeader('X-Deprecated');

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('implements withBody', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $stream = Mockery::mock(StreamInterface::class);
        $psrResponse->shouldReceive('withBody')->with($stream)->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withBody($stream);

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('implements withStatus', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $newPsrResponse = Mockery::mock(PsrResponseInterface::class);
        $psrResponse->shouldReceive('withStatus')->with(404, 'Not Found')->andReturn($newPsrResponse);

        $response = new Response($request, $psrResponse);
        $newResponse = $response->withStatus(404, 'Not Found');

        expect($newResponse)->not->toBe($response)
            ->and($newResponse)->toBeInstanceOf(Response::class);
    });

    it('can get serializer', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $response = new Response($request, $psrResponse);

        expect($response->getSerializer())->toBeInstanceOf(\Farzai\Transport\Contracts\SerializerInterface::class);
    });

    it('can get PSR request', function () {
        $request = Mockery::mock(RequestInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $response = new Response($request, $psrResponse);

        expect($response->getPsrRequest())->toBe($request);
    });
});

afterEach(function () {
    Mockery::close();
});
