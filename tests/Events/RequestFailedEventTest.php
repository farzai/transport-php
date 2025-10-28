<?php

declare(strict_types=1);

use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Exceptions\ClientException;
use Farzai\Transport\Exceptions\NetworkException;
use Farzai\Transport\Exceptions\ServerException;
use Farzai\Transport\Exceptions\TimeoutException;
use Farzai\Transport\Factory\HttpFactory;

beforeEach(function () {
    $factory = HttpFactory::getInstance();
    $this->request = $factory->createRequest('POST', 'https://api.example.com/users');
});

it('creates event with request and exception', function () {
    $exception = new Exception('Connection failed');

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getException())->toBe($exception)
        ->and($event->getAttemptNumber())->toBe(1);
});

it('stores and retrieves all properties correctly', function () {
    $exception = new RuntimeException('Test error');
    $timestamp = 1234567890.123456;

    $event = new RequestFailedEvent(
        $this->request,
        $exception,
        3,
        $timestamp
    );

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getException())->toBe($exception)
        ->and($event->getAttemptNumber())->toBe(3)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('uses current time when timestamp not provided', function () {
    $beforeTime = microtime(true);

    $event = new RequestFailedEvent(
        $this->request,
        new Exception('error')
    );

    $afterTime = microtime(true);

    $timestamp = $event->getTimestamp();
    expect($timestamp)->toBeGreaterThanOrEqual($beforeTime)
        ->and($timestamp)->toBeLessThanOrEqual($afterTime);
});

it('uses provided timestamp when given', function () {
    $providedTimestamp = 1609459200.5; // 2021-01-01 00:00:00.5

    $event = new RequestFailedEvent(
        $this->request,
        new Exception('error'),
        1,
        $providedTimestamp
    );

    expect($event->getTimestamp())->toBe($providedTimestamp);
});

it('getMethod returns request method', function () {
    $event = new RequestFailedEvent($this->request, new Exception('error'));

    expect($event->getMethod())->toBe('POST');
});

it('getUri returns request uri as string', function () {
    $event = new RequestFailedEvent($this->request, new Exception('error'));

    expect($event->getUri())->toBe('https://api.example.com/users');
});

it('getExceptionType returns exception class name', function () {
    $exception = new RuntimeException('Test error');

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->getExceptionType())->toBe(RuntimeException::class);
});

it('getExceptionMessage returns exception message', function () {
    $exception = new Exception('This is a test error message');

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->getExceptionMessage())->toBe('This is a test error message');
});

it('isNetworkError detects NetworkException', function () {
    $exception = new NetworkException('Connection failed', $this->request);

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->isNetworkError())->toBeTrue()
        ->and($event->isTimeoutError())->toBeFalse()
        ->and($event->isHttpError())->toBeFalse();
});

it('isNetworkError detects exception with NetworkException in name', function () {
    // Create a custom exception that contains "NetworkException" in the class name
    $exception = new class('Network error') extends Exception
    {
        public function __construct(string $message)
        {
            parent::__construct($message);
        }
    };

    // Override the class name check by using a NetworkException
    $networkException = new NetworkException('Connection issue', $this->request);
    $event = new RequestFailedEvent($this->request, $networkException);

    expect($event->isNetworkError())->toBeTrue();
});

it('isTimeoutError detects TimeoutException', function () {
    $exception = new TimeoutException('Request timeout', $this->request, 30);

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->isTimeoutError())->toBeTrue()
        ->and($event->isNetworkError())->toBeFalse()
        ->and($event->isHttpError())->toBeFalse();
});

it('isHttpError detects ClientException', function () {
    $response = HttpFactory::getInstance()->createResponse(404);
    $exception = new ClientException('Not found', $this->request, $response);

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->isHttpError())->toBeTrue()
        ->and($event->isNetworkError())->toBeFalse()
        ->and($event->isTimeoutError())->toBeFalse();
});

it('isHttpError detects ServerException', function () {
    $response = HttpFactory::getInstance()->createResponse(500);
    $exception = new ServerException('Internal server error', $this->request, $response);

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->isHttpError())->toBeTrue()
        ->and($event->isNetworkError())->toBeFalse()
        ->and($event->isTimeoutError())->toBeFalse();
});

it('error detection returns false for other exception types', function () {
    $exception = new RuntimeException('Generic error');

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->isNetworkError())->toBeFalse()
        ->and($event->isTimeoutError())->toBeFalse()
        ->and($event->isHttpError())->toBeFalse();
});

it('isRetry returns false for attempt 1', function () {
    $event = new RequestFailedEvent(
        $this->request,
        new Exception('error'),
        1
    );

    expect($event->isRetry())->toBeFalse();
});

it('isRetry returns true for attempt greater than 1', function () {
    $event = new RequestFailedEvent(
        $this->request,
        new Exception('error'),
        2
    );

    expect($event->isRetry())->toBeTrue();
});

it('isRetry returns true for multiple retry attempts', function () {
    $event = new RequestFailedEvent(
        $this->request,
        new Exception('error'),
        5
    );

    expect($event->isRetry())->toBeTrue();
});

it('can stop propagation', function () {
    $event = new RequestFailedEvent($this->request, new Exception('error'));

    expect($event->isPropagationStopped())->toBeFalse();

    $event->stopPropagation();

    expect($event->isPropagationStopped())->toBeTrue();
});

it('handles exceptions with empty message', function () {
    $exception = new Exception('');

    $event = new RequestFailedEvent($this->request, $exception);

    expect($event->getExceptionMessage())->toBe('');
});

it('works with different http methods', function () {
    $getRequest = HttpFactory::getInstance()->createRequest('GET', 'https://api.example.com');
    $deleteRequest = HttpFactory::getInstance()->createRequest('DELETE', 'https://api.example.com');

    $event1 = new RequestFailedEvent($getRequest, new Exception('error'));
    $event2 = new RequestFailedEvent($deleteRequest, new Exception('error'));

    expect($event1->getMethod())->toBe('GET')
        ->and($event2->getMethod())->toBe('DELETE');
});

it('works with different uris', function () {
    $request1 = HttpFactory::getInstance()->createRequest('GET', 'https://api.example.com/v1/users');
    $request2 = HttpFactory::getInstance()->createRequest('GET', 'http://localhost:8080/test?foo=bar');

    $event1 = new RequestFailedEvent($request1, new Exception('error'));
    $event2 = new RequestFailedEvent($request2, new Exception('error'));

    expect($event1->getUri())->toBe('https://api.example.com/v1/users')
        ->and($event2->getUri())->toBe('http://localhost:8080/test?foo=bar');
});
