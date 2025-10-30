<?php

declare(strict_types=1);

use Farzai\Transport\Events\EventDispatcher;
use Farzai\Transport\Events\EventInterface;
use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Events\RequestSendingEvent;
use Farzai\Transport\Events\RequestSentEvent;
use Farzai\Transport\Events\ResponseReceivedEvent;
use Farzai\Transport\Factory\HttpFactory;
use Farzai\Transport\Middleware\EventMiddleware;

beforeEach(function () {
    $this->dispatcher = new EventDispatcher;
    $this->middleware = new EventMiddleware($this->dispatcher);
    $this->dispatchedEvents = [];

    // Register listeners that capture all event types
    $captureEvent = function (EventInterface $event) {
        $this->dispatchedEvents[] = $event;
    };

    $this->dispatcher->addEventListener(RequestSendingEvent::class, $captureEvent);
    $this->dispatcher->addEventListener(RequestSentEvent::class, $captureEvent);
    $this->dispatcher->addEventListener(ResponseReceivedEvent::class, $captureEvent);
    $this->dispatcher->addEventListener(RequestFailedEvent::class, $captureEvent);
});

it('dispatches RequestSendingEvent before request', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $this->middleware->handle($request, fn ($req) => $response);

    // First event should be RequestSendingEvent
    expect($this->dispatchedEvents[0])->toBeInstanceOf(RequestSendingEvent::class)
        ->and($this->dispatchedEvents[0]->getRequest())->toBe($request);
});

it('dispatches RequestSentEvent after successful response', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('POST', 'https://api.example.com');
    $response = $factory->createResponse(201);

    $this->middleware->handle($request, fn ($req) => $response);

    // Second event should be RequestSentEvent
    expect($this->dispatchedEvents[1])->toBeInstanceOf(RequestSentEvent::class)
        ->and($this->dispatchedEvents[1]->getRequest())->toBe($request)
        ->and($this->dispatchedEvents[1]->getResponse())->toBe($response);
});

it('dispatches ResponseReceivedEvent after middleware processing', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $this->middleware->handle($request, fn ($req) => $response);

    // Third event should be ResponseReceivedEvent
    expect($this->dispatchedEvents[2])->toBeInstanceOf(ResponseReceivedEvent::class)
        ->and($this->dispatchedEvents[2]->getRequest())->toBe($request)
        ->and($this->dispatchedEvents[2]->getResponse())->toBe($response);
});

it('dispatches RequestFailedEvent on exception', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $exception = new Exception('Connection failed');

    try {
        $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });
        expect(true)->toBeFalse('Exception should have been thrown');
    } catch (Exception $e) {
        // Expected
    }

    // Should have RequestSendingEvent and RequestFailedEvent
    expect($this->dispatchedEvents)->toHaveCount(2)
        ->and($this->dispatchedEvents[0])->toBeInstanceOf(RequestSendingEvent::class)
        ->and($this->dispatchedEvents[1])->toBeInstanceOf(RequestFailedEvent::class)
        ->and($this->dispatchedEvents[1]->getRequest())->toBe($request)
        ->and($this->dispatchedEvents[1]->getException())->toBe($exception);
});

it('re-throws exception after dispatching failure event', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $exception = new Exception('Test error');

    expect(fn () => $this->middleware->handle($request, function () use ($exception) {
        throw $exception;
    }))->toThrow(Exception::class, 'Test error');
});

it('calculates durations correctly in milliseconds', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    // Simulate some processing time
    $this->middleware->handle($request, function ($req) use ($response) {
        usleep(5000); // 5ms delay

        return $response;
    });

    /** @var RequestSentEvent $sentEvent */
    $sentEvent = $this->dispatchedEvents[1];
    /** @var ResponseReceivedEvent $receivedEvent */
    $receivedEvent = $this->dispatchedEvents[2];

    // Durations should be in milliseconds and greater than 0
    expect($sentEvent->getDuration())->toBeGreaterThan(0)
        ->and($receivedEvent->getDuration())->toBeGreaterThan(0)
        // Duration should be at least 4ms (we waited 5ms, allow some margin)
        ->and($sentEvent->getDuration())->toBeGreaterThanOrEqual(4.0);
});

it('RequestSentEvent has different duration than ResponseReceivedEvent', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $this->middleware->handle($request, fn ($req) => $response);

    /** @var RequestSentEvent $sentEvent */
    $sentEvent = $this->dispatchedEvents[1];
    /** @var ResponseReceivedEvent $receivedEvent */
    $receivedEvent = $this->dispatchedEvents[2];

    // ResponseReceivedEvent duration should be >= RequestSentEvent duration
    // (includes time for dispatching RequestSentEvent)
    expect($receivedEvent->getDuration())->toBeGreaterThanOrEqual($sentEvent->getDuration());
});

it('getEventDispatcher returns dispatcher instance', function () {
    expect($this->middleware->getEventDispatcher())->toBe($this->dispatcher);
});

it('events contain correct request response data', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('POST', 'https://api.example.com/users');
    $response = $factory->createResponse(201);

    $this->middleware->handle($request, fn ($req) => $response);

    /** @var RequestSendingEvent $sendingEvent */
    $sendingEvent = $this->dispatchedEvents[0];
    /** @var RequestSentEvent $sentEvent */
    $sentEvent = $this->dispatchedEvents[1];
    /** @var ResponseReceivedEvent $receivedEvent */
    $receivedEvent = $this->dispatchedEvents[2];

    // Check request consistency
    expect($sendingEvent->getMethod())->toBe('POST')
        ->and($sentEvent->getMethod())->toBe('POST')
        ->and($receivedEvent->getMethod())->toBe('POST');

    expect($sendingEvent->getUri())->toBe('https://api.example.com/users')
        ->and($sentEvent->getUri())->toBe('https://api.example.com/users')
        ->and($receivedEvent->getUri())->toBe('https://api.example.com/users');

    // Check response consistency
    expect($sentEvent->getStatusCode())->toBe(201)
        ->and($receivedEvent->getStatusCode())->toBe(201);
});

it('works with multiple listeners', function () {
    $sendingEventCount = 0;
    $sentEventCount = 0;
    $receivedEventCount = 0;

    $this->dispatcher->addEventListener(RequestSendingEvent::class, function () use (&$sendingEventCount) {
        $sendingEventCount++;
    });

    $this->dispatcher->addEventListener(RequestSentEvent::class, function () use (&$sentEventCount) {
        $sentEventCount++;
    });

    $this->dispatcher->addEventListener(ResponseReceivedEvent::class, function () use (&$receivedEventCount) {
        $receivedEventCount++;
    });

    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $this->middleware->handle($request, fn ($req) => $response);

    expect($sendingEventCount)->toBe(1)
        ->and($sentEventCount)->toBe(1)
        ->and($receivedEventCount)->toBe(1);
});

it('dispatches events in correct order', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $this->middleware->handle($request, fn ($req) => $response);

    expect($this->dispatchedEvents)->toHaveCount(3)
        ->and($this->dispatchedEvents[0])->toBeInstanceOf(RequestSendingEvent::class)
        ->and($this->dispatchedEvents[1])->toBeInstanceOf(RequestSentEvent::class)
        ->and($this->dispatchedEvents[2])->toBeInstanceOf(ResponseReceivedEvent::class);
});

it('event timestamps are captured correctly', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $beforeTime = microtime(true);

    $this->middleware->handle($request, fn ($req) => $response);

    $afterTime = microtime(true);

    /** @var RequestSendingEvent $sendingEvent */
    $sendingEvent = $this->dispatchedEvents[0];
    /** @var RequestSentEvent $sentEvent */
    $sentEvent = $this->dispatchedEvents[1];
    /** @var ResponseReceivedEvent $receivedEvent */
    $receivedEvent = $this->dispatchedEvents[2];

    // All timestamps should be within the test execution window
    expect($sendingEvent->getTimestamp())->toBeGreaterThanOrEqual($beforeTime)
        ->and($receivedEvent->getTimestamp())->toBeLessThanOrEqual($afterTime);

    // Timestamps should be in chronological order (allowing small floating point differences)
    expect($receivedEvent->getTimestamp())->toBeLessThanOrEqual($sentEvent->getTimestamp() + 0.001)
        ->and($sentEvent->getTimestamp())->toBeLessThanOrEqual($sendingEvent->getTimestamp() + 0.001);
});

it('RequestFailedEvent has attempt number 1', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');

    try {
        $this->middleware->handle($request, function () {
            throw new Exception('Error');
        });
    } catch (Exception $e) {
        // Expected
    }

    /** @var RequestFailedEvent $failedEvent */
    $failedEvent = $this->dispatchedEvents[1];

    expect($failedEvent->getAttemptNumber())->toBe(1);
});

it('returns response from next callable', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $expectedResponse = $factory->createResponse(200);

    $actualResponse = $this->middleware->handle($request, fn ($req) => $expectedResponse);

    expect($actualResponse)->toBe($expectedResponse);
});

it('passes request to next callable', function () {
    $factory = HttpFactory::getInstance();
    $request = $factory->createRequest('GET', 'https://api.example.com');
    $response = $factory->createResponse(200);

    $receivedRequest = null;

    $this->middleware->handle($request, function ($req) use ($response, &$receivedRequest) {
        $receivedRequest = $req;

        return $response;
    });

    expect($receivedRequest)->toBe($request);
});
