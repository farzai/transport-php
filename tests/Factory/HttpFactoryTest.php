<?php

declare(strict_types=1);

use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

describe('HttpFactory', function () {
    afterEach(function () {
        // Reset singleton between tests
        HttpFactory::resetInstance();
    });

    it('can create singleton instance', function () {
        $instance1 = HttpFactory::getInstance();
        $instance2 = HttpFactory::getInstance();

        expect($instance1)->toBe($instance2);
    });

    it('resetInstance clears singleton', function () {
        $instance1 = HttpFactory::getInstance();
        HttpFactory::resetInstance();
        $instance2 = HttpFactory::getInstance();

        expect($instance1)->not->toBe($instance2);
    });

    it('can create request', function () {
        $factory = HttpFactory::getInstance();
        $request = $factory->createRequest('GET', 'https://example.com');

        expect($request)->toBeInstanceOf(RequestInterface::class);
    });

    it('can create response', function () {
        $factory = HttpFactory::getInstance();
        $response = $factory->createResponse();

        expect($response)->toBeInstanceOf(ResponseInterface::class)
            ->and($response->getStatusCode())->toBe(200);
    });

    it('can create response with status code and reason', function () {
        $factory = HttpFactory::getInstance();
        $response = $factory->createResponse(404, 'Not Found');

        expect($response)->toBeInstanceOf(ResponseInterface::class)
            ->and($response->getStatusCode())->toBe(404)
            ->and($response->getReasonPhrase())->toBe('Not Found');
    });

    it('can create URI', function () {
        $factory = HttpFactory::getInstance();
        $uri = $factory->createUri('https://api.example.com/users');

        expect($uri)->toBeInstanceOf(UriInterface::class)
            ->and((string) $uri)->toBe('https://api.example.com/users');
    });

    it('can create empty URI', function () {
        $factory = HttpFactory::getInstance();
        $uri = $factory->createUri();

        expect($uri)->toBeInstanceOf(UriInterface::class);
    });

    it('can create stream', function () {
        $factory = HttpFactory::getInstance();
        $stream = $factory->createStream('test content');

        expect($stream)->toBeInstanceOf(StreamInterface::class)
            ->and($stream->getContents())->toBe('test content');
    });

    it('can create empty stream', function () {
        $factory = HttpFactory::getInstance();
        $stream = $factory->createStream();

        expect($stream)->toBeInstanceOf(StreamInterface::class);
    });

    it('can create stream from resource', function () {
        $factory = HttpFactory::getInstance();
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'resource content');
        rewind($resource);

        $stream = $factory->createStreamFromResource($resource);

        expect($stream)->toBeInstanceOf(StreamInterface::class)
            ->and($stream->getContents())->toBe('resource content');

        fclose($resource);
    });

    it('can create stream from file', function () {
        $filename = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($filename, 'file content');

        $factory = HttpFactory::getInstance();
        $stream = $factory->createStreamFromFile($filename, 'r');

        expect($stream)->toBeInstanceOf(StreamInterface::class)
            ->and($stream->getContents())->toBe('file content');

        unlink($filename);
    });

    it('can use custom request factory', function () {
        $customRequestFactory = Mockery::mock(RequestFactoryInterface::class);
        $mockRequest = Mockery::mock(RequestInterface::class);
        $customRequestFactory->shouldReceive('createRequest')
            ->with('POST', 'https://example.com')
            ->andReturn($mockRequest);

        $factory = new HttpFactory(requestFactory: $customRequestFactory);
        $request = $factory->createRequest('POST', 'https://example.com');

        expect($request)->toBe($mockRequest);
    });

    it('can use custom response factory', function () {
        $customResponseFactory = Mockery::mock(ResponseFactoryInterface::class);
        $mockResponse = Mockery::mock(ResponseInterface::class);
        $customResponseFactory->shouldReceive('createResponse')
            ->with(201, 'Created')
            ->andReturn($mockResponse);

        $factory = new HttpFactory(responseFactory: $customResponseFactory);
        $response = $factory->createResponse(201, 'Created');

        expect($response)->toBe($mockResponse);
    });

    it('can use custom uri factory', function () {
        $customUriFactory = Mockery::mock(UriFactoryInterface::class);
        $mockUri = Mockery::mock(UriInterface::class);
        $customUriFactory->shouldReceive('createUri')
            ->with('https://custom.com')
            ->andReturn($mockUri);

        $factory = new HttpFactory(uriFactory: $customUriFactory);
        $uri = $factory->createUri('https://custom.com');

        expect($uri)->toBe($mockUri);
    });

    it('can use custom stream factory', function () {
        $customStreamFactory = Mockery::mock(StreamFactoryInterface::class);
        $mockStream = Mockery::mock(StreamInterface::class);
        $customStreamFactory->shouldReceive('createStream')
            ->with('custom content')
            ->andReturn($mockStream);

        $factory = new HttpFactory(streamFactory: $customStreamFactory);
        $stream = $factory->createStream('custom content');

        expect($stream)->toBe($mockStream);
    });

    it('auto-detects request factory when not provided', function () {
        $factory = new HttpFactory();
        $request = $factory->createRequest('GET', 'https://example.com');

        expect($request)->toBeInstanceOf(RequestInterface::class);
    });

    it('auto-detects response factory when not provided', function () {
        $factory = new HttpFactory();
        $response = $factory->createResponse(200);

        expect($response)->toBeInstanceOf(ResponseInterface::class);
    });

    it('auto-detects uri factory when not provided', function () {
        $factory = new HttpFactory();
        $uri = $factory->createUri('https://example.com');

        expect($uri)->toBeInstanceOf(UriInterface::class);
    });

    it('auto-detects stream factory when not provided', function () {
        $factory = new HttpFactory();
        $stream = $factory->createStream('test');

        expect($stream)->toBeInstanceOf(StreamInterface::class);
    });

    it('can chain multiple factory creations', function () {
        $factory = HttpFactory::getInstance();

        $request = $factory->createRequest('POST', 'https://api.example.com');
        $response = $factory->createResponse(200);
        $uri = $factory->createUri('https://example.com');
        $stream = $factory->createStream('body');

        expect($request)->toBeInstanceOf(RequestInterface::class)
            ->and($response)->toBeInstanceOf(ResponseInterface::class)
            ->and($uri)->toBeInstanceOf(UriInterface::class)
            ->and($stream)->toBeInstanceOf(StreamInterface::class);
    });
});

afterEach(function () {
    Mockery::close();
});
