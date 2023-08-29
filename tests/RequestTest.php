<?php

use Farzai\Transport\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\StreamInterface;

it('can get the request method', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    expect($request->getMethod())->toBe('GET');
});

it('can set the request method', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    $newRequest = $request->withMethod('POST');

    expect($newRequest->getMethod())->toBe('POST');
});

it('can get request target', function () {
    $request = new Request('GET', new Uri('https://example.com/foo'));

    expect($request->getRequestTarget())->toBe('/foo');
});

it('can set request target', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    $newRequest = $request->withRequestTarget('/foo');

    expect($newRequest->getRequestTarget())->toBe('/foo');
});

it('can get the request URI', function () {
    $uri = new Uri('https://example.com');
    $request = new Request('GET', $uri);

    expect($request->getUri())->toBe($uri);
});

it('can set the request URI', function () {
    $uri = new Uri('https://example.com');
    $request = new Request('GET', $uri);

    $newUri = new Uri('https://example.org');
    $newRequest = $request->withUri($newUri);

    expect($newRequest->getUri())->toBe($newUri);
});

it('can get the request protocol version', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    expect($request->getProtocolVersion())->toBe('1.1');
});

it('can set the request protocol version', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    $newRequest = $request->withProtocolVersion('2.0');

    expect($newRequest->getProtocolVersion())->toBe('2.0');
});

it('can get the request headers', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
    ]);

    expect($request->getHeaders())->toBe([
        'Host' => ['example.com'],
        'Content-Type' => ['application/json'],
    ]);
});

it('can set a request header', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    $newRequest = $request->withHeader('Content-Type', 'application/json');

    expect($newRequest->getHeaders())->toBe([
        'Host' => ['example.com'],
        'Content-Type' => ['application/json'],
    ]);
});

it('can add a request header', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
    ]);

    $newRequest = $request->withAddedHeader('Accept', 'application/json');

    expect($newRequest->getHeaders())->toBe([
        'Host' => ['example.com'],
        'Content-Type' => ['application/json'],
        'Accept' => ['application/json'],
    ]);
});

it('can check exists header', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
    ]);

    expect($request->hasHeader('Content-Type'))->toBeTrue();
    expect($request->hasHeader('Accept'))->toBeFalse();
});

it('can get header', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
    ]);

    expect($request->getHeader('Content-Type'))->toBe(['application/json']);
    expect($request->getHeader('Accept'))->toBe([]);
});

it('can remove a request header', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]);

    $newRequest = $request->withoutHeader('Accept');

    expect($newRequest->getHeaders())->toBe([
        'Host' => ['example.com'],
        'Content-Type' => ['application/json'],
    ]);
});

it('can get a request header line', function () {
    $request = new Request('GET', new Uri('https://example.com'), [
        'Content-Type' => 'application/json',
    ]);

    expect($request->getHeaderLine('Content-Type'))->toBe('application/json');
});

it('can set the request body', function () {
    $request = new Request('GET', new Uri('https://example.com'));

    $stream = $this->createMock(StreamInterface::class);
    $stream->method('__toString')->willReturn('{"foo":"bar"}');

    $newRequest = $request->withBody($stream);

    expect((string) $newRequest->getBody())->toBe('{"foo":"bar"}');
});
