<?php

use Farzai\Transport\Exceptions\ResponseExceptionFactory;
use Farzai\Transport\Response;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;

it('should throw bad request', function () {
    $stream = $this->createMock(PsrStreamInterface::class);
    $stream->method('getContents')->willReturn('{"message":"Bad Request"}');

    $response = $this->createMock(PsrResponseInterface::class);
    $response->method('getStatusCode')->willReturn(400);
    $response->method('getBody')->willReturn($stream);
    $response
        ->method('getHeaders')
        ->willReturn(['Content-Type' => ['application/json']]);

    $psrRequest = $this->createMock(PsrRequestInterface::class);

    $exception = ResponseExceptionFactory::create(
        new Response($psrRequest, $response)
    );

    expect($exception)->toBeInstanceOf(BadResponseException::class);
    expect($exception->getMessage())->toBe('Bad Request');
    expect($exception->getCode())->toBe(400);
});

it('should throw server error', function () {
    $stream = $this->createMock(PsrStreamInterface::class);
    $stream->method('getContents')->willReturn('{"message":"Internal Server Error"}');

    $response = $this->createMock(PsrResponseInterface::class);
    $response->method('getStatusCode')->willReturn(500);
    $response->method('getBody')->willReturn($stream);
    $response
        ->method('getHeaders')
        ->willReturn(['Content-Type' => ['application/json']]);

    $psrRequest = $this->createMock(PsrRequestInterface::class);

    $exception = ResponseExceptionFactory::create(
        new Response($psrRequest, $response)
    );

    expect($exception)->toBeInstanceOf(ServerException::class);
    expect($exception->getMessage())->toBe('Internal Server Error');
    expect($exception->getCode())->toBe(500);
});
