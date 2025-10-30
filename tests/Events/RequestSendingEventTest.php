<?php

declare(strict_types=1);

use Farzai\Transport\Events\RequestSendingEvent;
use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\RequestInterface;

beforeEach(function () {
    $factory = HttpFactory::getInstance();
    $this->request = $factory->createRequest('GET', 'https://api.example.com/users');
});

it('creates event with request', function () {
    $event = new RequestSendingEvent($this->request);

    expect($event->getRequest())->toBe($this->request);
});

it('uses current time when timestamp not provided', function () {
    $beforeTime = microtime(true);

    $event = new RequestSendingEvent($this->request);

    $afterTime = microtime(true);

    $timestamp = $event->getTimestamp();
    expect($timestamp)->toBeGreaterThanOrEqual($beforeTime)
        ->and($timestamp)->toBeLessThanOrEqual($afterTime);
});

it('uses provided timestamp when given', function () {
    $providedTimestamp = 1609459200.5; // 2021-01-01 00:00:00.5

    $event = new RequestSendingEvent($this->request, $providedTimestamp);

    expect($event->getTimestamp())->toBe($providedTimestamp);
});

it('getRequest returns PSR7 request', function () {
    $event = new RequestSendingEvent($this->request);

    expect($event->getRequest())->toBeInstanceOf(RequestInterface::class)
        ->and($event->getRequest())->toBe($this->request);
});

it('getMethod returns HTTP method', function () {
    $postRequest = HttpFactory::getInstance()->createRequest('POST', 'https://api.example.com');

    $event = new RequestSendingEvent($postRequest);

    expect($event->getMethod())->toBe('POST');
});

it('getUri returns URI as string', function () {
    $event = new RequestSendingEvent($this->request);

    expect($event->getUri())->toBe('https://api.example.com/users');
});

it('can stop propagation', function () {
    $event = new RequestSendingEvent($this->request);

    expect($event->isPropagationStopped())->toBeFalse();

    $event->stopPropagation();

    expect($event->isPropagationStopped())->toBeTrue();
});

it('works with different http methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    foreach ($methods as $method) {
        $request = HttpFactory::getInstance()->createRequest($method, 'https://api.example.com');
        $event = new RequestSendingEvent($request);

        expect($event->getMethod())->toBe($method);
    }
});

it('works with different uris', function () {
    $uris = [
        'https://api.example.com',
        'http://localhost:8080',
        'https://example.com/api/v1/users?page=1',
    ];

    foreach ($uris as $uri) {
        $request = HttpFactory::getInstance()->createRequest('GET', $uri);
        $event = new RequestSendingEvent($request);

        expect($event->getUri())->toBe($uri);
    }
});
