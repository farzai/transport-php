<?php

declare(strict_types=1);

use Farzai\Transport\Events\ResponseReceivedEvent;
use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

beforeEach(function () {
    $this->factory = HttpFactory::getInstance();
    $this->request = $this->factory->createRequest('GET', 'https://api.example.com/data');
    $this->response = $this->factory->createResponse(200);
});

it('creates event with all parameters', function () {
    $duration = 150.25;
    $timestamp = 1234567890.123456;

    $event = new ResponseReceivedEvent(
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

it('returns request response duration timestamp', function () {
    $duration = 200.0;
    $timestamp = microtime(true);

    $event = new ResponseReceivedEvent(
        $this->request,
        $this->response,
        $duration,
        $timestamp
    );

    expect($event->getRequest())->toBeInstanceOf(RequestInterface::class)
        ->and($event->getResponse())->toBeInstanceOf(ResponseInterface::class)
        ->and($event->getDuration())->toBe($duration)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('getMethod and getUri work correctly', function () {
    $event = new ResponseReceivedEvent(
        $this->request,
        $this->response,
        100.0,
        microtime(true)
    );

    expect($event->getMethod())->toBe('GET')
        ->and($event->getUri())->toBe('https://api.example.com/data');
});

it('getStatusCode returns correct status', function () {
    $response201 = $this->factory->createResponse(201);

    $event = new ResponseReceivedEvent(
        $this->request,
        $response201,
        100.0,
        microtime(true)
    );

    expect($event->getStatusCode())->toBe(201);
});

it('isSuccessful detects 2xx codes', function () {
    $successCodes = [200, 201, 202, 204, 299];

    foreach ($successCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new ResponseReceivedEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isSuccessful())->toBeTrue("Status $code should be successful")
            ->and($event->isClientError())->toBeFalse("Status $code should not be client error")
            ->and($event->isServerError())->toBeFalse("Status $code should not be server error");
    }
});

it('isClientError detects 4xx codes', function () {
    $clientErrorCodes = [400, 401, 403, 404, 422, 429, 499];

    foreach ($clientErrorCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new ResponseReceivedEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isClientError())->toBeTrue("Status $code should be client error")
            ->and($event->isSuccessful())->toBeFalse("Status $code should not be successful")
            ->and($event->isServerError())->toBeFalse("Status $code should not be server error");
    }
});

it('isServerError detects 5xx codes', function () {
    $serverErrorCodes = [500, 501, 502, 503, 504, 599];

    foreach ($serverErrorCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new ResponseReceivedEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isServerError())->toBeTrue("Status $code should be server error")
            ->and($event->isSuccessful())->toBeFalse("Status $code should not be successful")
            ->and($event->isClientError())->toBeFalse("Status $code should not be client error");
    }
});

it('status helpers return false for other ranges', function () {
    $otherCodes = [100, 101, 199, 300, 301, 302, 399];

    foreach ($otherCodes as $code) {
        $response = $this->factory->createResponse($code);
        $event = new ResponseReceivedEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isSuccessful())->toBeFalse("Status $code should not be successful")
            ->and($event->isClientError())->toBeFalse("Status $code should not be client error")
            ->and($event->isServerError())->toBeFalse("Status $code should not be server error");
    }
});

it('can stop propagation', function () {
    $event = new ResponseReceivedEvent(
        $this->request,
        $this->response,
        100.0,
        microtime(true)
    );

    expect($event->isPropagationStopped())->toBeFalse();

    $event->stopPropagation();

    expect($event->isPropagationStopped())->toBeTrue();
});

it('handles edge of status code ranges', function () {
    // Test boundary values
    $boundaries = [
        199 => ['successful' => false, 'clientError' => false, 'serverError' => false],
        200 => ['successful' => true, 'clientError' => false, 'serverError' => false],
        299 => ['successful' => true, 'clientError' => false, 'serverError' => false],
        300 => ['successful' => false, 'clientError' => false, 'serverError' => false],
        399 => ['successful' => false, 'clientError' => false, 'serverError' => false],
        400 => ['successful' => false, 'clientError' => true, 'serverError' => false],
        499 => ['successful' => false, 'clientError' => true, 'serverError' => false],
        500 => ['successful' => false, 'clientError' => false, 'serverError' => true],
        599 => ['successful' => false, 'clientError' => false, 'serverError' => true],
        600 => ['successful' => false, 'clientError' => false, 'serverError' => false],
    ];

    foreach ($boundaries as $code => $expected) {
        $response = $this->factory->createResponse($code);
        $event = new ResponseReceivedEvent($this->request, $response, 100.0, microtime(true));

        expect($event->isSuccessful())->toBe(
            $expected['successful'],
            "Status $code successful check failed"
        );
        expect($event->isClientError())->toBe(
            $expected['clientError'],
            "Status $code client error check failed"
        );
        expect($event->isServerError())->toBe(
            $expected['serverError'],
            "Status $code server error check failed"
        );
    }
});

it('works with different http methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    foreach ($methods as $method) {
        $request = $this->factory->createRequest($method, 'https://api.example.com');
        $event = new ResponseReceivedEvent($request, $this->response, 100.0, microtime(true));

        expect($event->getMethod())->toBe($method);
    }
});

it('handles various durations', function () {
    $durations = [0.0, 50.5, 150.75, 1000.0, 5000.123];

    foreach ($durations as $duration) {
        $event = new ResponseReceivedEvent(
            $this->request,
            $this->response,
            $duration,
            microtime(true)
        );

        expect($event->getDuration())->toBe($duration);
    }
});
