<?php

use Farzai\Transport\Response;
use Mockery as Mock;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('can get the response status code', function () {
    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(200)
            ->getMock()
    );

    $this->assertEquals(200, $response->statusCode());
});

it('can get the response body', function () {
    $stream = Mock::mock(StreamInterface::class)
        ->shouldReceive('getContents')
        ->once()
        ->andReturn('{"foo":"bar"}')
        ->getMock();

    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($stream)
            ->getMock()
    );

    $this->assertEquals('{"foo":"bar"}', $response->body());
});

it('can get the response headers', function () {
    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getHeaders')
            ->once()
            ->andReturn(['Content-Type' => ['application/json']])
            ->getMock()
    );

    $this->assertEquals(['Content-Type' => ['application/json']], $response->headers());
});

it('can check if the response is successfull', function () {
    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getStatusCode')
            ->andReturn(200)
            ->getMock()
    );

    $this->assertTrue($response->isSuccessfull());
});

it('can check if the response is not successfull', function () {
    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getStatusCode')
            ->andReturn(400)
            ->getMock()
    );

    $this->assertFalse($response->isSuccessfull());
});

it('can get json body as array', function () {
    $stream = Mock::mock(StreamInterface::class)
        ->shouldReceive('getContents')
        ->once()
        ->andReturn('{"foo":"bar"}')
        ->getMock();

    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($stream)
            ->getMock()
    );

    $this->assertEquals(['foo' => 'bar'], $response->json());
});

it('cannot get json when invalid json format', function () {
    $stream = Mock::mock(StreamInterface::class)
        ->shouldReceive('getContents')
        ->once()
        ->andReturn('{"foo":"bar"')
        ->getMock();

    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($stream)
            ->getMock()
    );

    expect($response->json())->toBeNull();
});

it('can specify key name to get json body', function () {
    $stream = Mock::mock(StreamInterface::class)
        ->shouldReceive('getContents')
        ->once()
        ->andReturn('{"foo":"bar"}')
        ->getMock();

    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($stream)
            ->getMock()
    );

    $this->assertEquals('bar', $response->json('foo'));
});

it('can throw error if response status is not success', function () {
    $stream = Mock::mock(StreamInterface::class)
        ->shouldReceive('getContents')
        ->once()
        ->andReturn('{"error": "invalid_request"}')
        ->getMock();

    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getStatusCode')
            ->andReturn(400)
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($stream)
            ->getMock()
    );

    $response->throw();
})->throws(\GuzzleHttp\Exception\BadResponseException::class);

it('should not throw error if response status is success', function () {
    $response = new Response(
        Mock::mock(RequestInterface::class),
        Mock::mock(ResponseInterface::class)
            ->shouldReceive('getStatusCode')
            ->andReturn(200)
            ->getMock()
    );

    $response->throw();
});