<?php

declare(strict_types=1);

use Farzai\Transport\Events\RequestSentEvent;
use Farzai\Transport\Factory\HttpFactory;

beforeEach(function () {
    $this->factory = HttpFactory::getInstance();
    $this->request = $this->factory->createRequest('POST', 'https://api.example.com/users');
    $this->response = $this->factory->createResponse(200);
});

it('creates event with all required parameters', function () {
    $duration = 123.45;
    $timestamp = 1234567890.123456;

    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        $duration,
        $timestamp
    );

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getResponse())->toBe($this->response)
        ->and($event->getDuration())->toBe($duration)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('returns correct request response duration timestamp', function () {
    $duration = 250.5;
    $timestamp = microtime(true);

    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        $duration,
        $timestamp
    );

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getResponse())->toBe($this->response)
        ->and($event->getDuration())->toBe($duration)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('getMethod returns HTTP method', function () {
    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        100.0,
        microtime(true)
    );

    expect($event->getMethod())->toBe('POST');
});

it('getUri returns URI string', function () {
    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        100.0,
        microtime(true)
    );

    expect($event->getUri())->toBe('https://api.example.com/users');
});

it('getStatusCode returns correct status', function () {
    $response404 = $this->factory->createResponse(404);

    $event = new RequestSentEvent(
        $this->request,
        $response404,
        100.0,
        microtime(true)
    );

    expect($event->getStatusCode())->toBe(404);
});

it('isSuccessful returns true for 2xx codes', function () {
    $successCodes = [200, 201, 202, 204, 299];

    foreach ($successCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new RequestSentEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isSuccessful())->toBeTrue("Status $code should be successful");
    }
});

it('isSuccessful returns false for non 2xx codes', function () {
    $failureCodes = [199, 300, 301, 400, 404, 500, 502];

    foreach ($failureCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new RequestSentEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isSuccessful())->toBeFalse("Status $code should not be successful");
    }
});

it('can stop propagation', function () {
    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        100.0,
        microtime(true)
    );

    expect($event->isPropagationStopped())->toBeFalse();

    $event->stopPropagation();

    expect($event->isPropagationStopped())->toBeTrue();
});

it('handles different durations', function () {
    $durations = [0.0, 1.5, 10.0, 100.5, 1000.0, 5000.75];

    foreach ($durations as $duration) {
        $event = new RequestSentEvent(
            $this->request,
            $this->response,
            $duration,
            microtime(true)
        );

        expect($event->getDuration())->toBe($duration);
    }
});

it('works with various http methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    foreach ($methods as $method) {
        $request = $this->factory->createRequest($method, 'https://api.example.com');
        $event = new RequestSentEvent($request, $this->response, 100.0, microtime(true));

        expect($event->getMethod())->toBe($method);
    }
});

it('works with various status codes', function () {
    $statusCodes = [200, 201, 301, 400, 404, 500, 503];

    foreach ($statusCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new RequestSentEvent($this->request, $response, 100.0, microtime(true));

        expect($event->getStatusCode())->toBe($code);
    }
});

it('handles zero duration', function () {
    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        0.0,
        microtime(true)
    );

    expect($event->getDuration())->toBe(0.0);
});

it('handles very long duration', function () {
    $longDuration = 10000.123; // 10 seconds

    $event = new RequestSentEvent(
        $this->request,
        $this->response,
        $longDuration,
        microtime(true)
    );

    expect($event->getDuration())->toBe($longDuration);
});
