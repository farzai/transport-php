<?php

use Farzai\Transport\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('can get the response status code', function () {
    $baseRequest = $this->createMock(RequestInterface::class);

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getStatusCode')->willReturn(200);

    $response = new Response(
        $baseRequest,
        $baseResponse,
    );

    expect($response->statusCode())->toBe(200);
    expect($response->isSuccessfull())->toBeTrue();
});

it('can get the response body', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"foo":"bar"}');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->body())->toBe('{"foo":"bar"}');
});

it('can get the response headers', function () {
    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->headers())->toBe(['Content-Type' => ['application/json']]);
});

it('can check if the response is not successfull', function () {
    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getStatusCode')->willReturn(400);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->isSuccessfull())->toBeFalse();
    expect($response->statusCode())->toBe(400);
});

it('can get json body as array', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"foo":"bar"}');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->json())->toBe(['foo' => 'bar']);
});

it('can get json body with dot notation', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"foo":{"bar":"baz"}}');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->json('foo.bar'))->toBe('baz');
});

it('cannot get json when invalid json format', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"foo":"bar"');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->json())->toBeNull();
});

it('can specify key name to get json body', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"foo":"bar"}');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    expect($response->json('foo'))->toBe('bar');
});

it('can throw error if response status is not success', function () {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn('{"error": "invalid_request"}');

    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getStatusCode')->willReturn(400);
    $baseResponse->method('getBody')->willReturn($stream);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    $response->throw();
})->throws(\GuzzleHttp\Exception\BadResponseException::class);

it('should not throw error if response status is success', function () {
    $baseResponse = $this->createMock(ResponseInterface::class);
    $baseResponse->method('getStatusCode')->willReturn(200);

    $response = new Response(
        $this->createMock(RequestInterface::class),
        $baseResponse,
    );

    $response->throw();

    expect($response->isSuccessfull())->toBeTrue();
});
