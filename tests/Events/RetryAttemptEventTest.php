<?php

declare(strict_types=1);

use Farzai\Transport\Events\RetryAttemptEvent;
use Farzai\Transport\Factory\HttpFactory;

beforeEach(function () {
    $factory = HttpFactory::getInstance();
    $this->request = $factory->createRequest('GET', 'https://api.example.com/data');
});

it('creates event with all parameters', function () {
    $reason = new RuntimeException('Connection timeout');

    $event = new RetryAttemptEvent(
        $this->request,
        $reason,
        2,
        5,
        1000
    );

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getReason())->toBe($reason)
        ->and($event->getAttemptNumber())->toBe(2)
        ->and($event->getMaxAttempts())->toBe(5)
        ->and($event->getDelay())->toBe(1000);
});

it('uses current time when timestamp not provided', function () {
    $beforeTime = microtime(true);

    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        500
    );

    $afterTime = microtime(true);

    $timestamp = $event->getTimestamp();
    expect($timestamp)->toBeGreaterThanOrEqual($beforeTime)
        ->and($timestamp)->toBeLessThanOrEqual($afterTime);
});

it('returns all properties correctly', function () {
    $reason = new Exception('Test error');
    $timestamp = 1234567890.123456;

    $event = new RetryAttemptEvent(
        $this->request,
        $reason,
        3,
        5,
        2000,
        $timestamp
    );

    expect($event->getRequest())->toBe($this->request)
        ->and($event->getReason())->toBe($reason)
        ->and($event->getAttemptNumber())->toBe(3)
        ->and($event->getMaxAttempts())->toBe(5)
        ->and($event->getDelay())->toBe(2000)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('getMethod returns request method', function () {
    $postRequest = HttpFactory::getInstance()->createRequest('POST', 'https://api.example.com');

    $event = new RetryAttemptEvent(
        $postRequest,
        new Exception('error'),
        2,
        3,
        1000
    );

    expect($event->getMethod())->toBe('POST');
});

it('getUri returns request uri as string', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        1000
    );

    expect($event->getUri())->toBe('https://api.example.com/data');
});

it('getRemainingAttempts calculates correctly', function () {
    // On attempt 2 out of 5 max attempts
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        5,
        1000
    );

    // Remaining = maxAttempts - attemptNumber + 1 = 5 - 2 + 1 = 4
    expect($event->getRemainingAttempts())->toBe(4);
});

it('getRemainingAttempts returns 1 on last attempt', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        5,
        5,
        1000
    );

    expect($event->getRemainingAttempts())->toBe(1);
});

it('isLastAttempt returns true when at max attempts', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        5,
        5,
        1000
    );

    expect($event->isLastAttempt())->toBeTrue();
});

it('isLastAttempt returns true when exceeding max attempts', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        6,
        5,
        1000
    );

    expect($event->isLastAttempt())->toBeTrue();
});

it('isLastAttempt returns false when more attempts remain', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        5,
        1000
    );

    expect($event->isLastAttempt())->toBeFalse();
});

it('getProgress calculates percentage correctly', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        5,
        1000
    );

    // Progress = (2 / 5) * 100 = 40%
    expect($event->getProgress())->toBe(40.0);
});

it('getProgress returns 100 for last attempt', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        5,
        5,
        1000
    );

    expect($event->getProgress())->toBe(100.0);
});

it('getProgress handles maxAttempts 1 edge case', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        1,
        1,
        1000
    );

    // When maxAttempts is 1, always return 100%
    expect($event->getProgress())->toBe(100.0);
});

it('getProgress calculates correctly for first retry', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        1,
        4,
        1000
    );

    // Progress = (1 / 4) * 100 = 25%
    expect($event->getProgress())->toBe(25.0);
});

it('getDelayInSeconds converts milliseconds correctly', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        1000
    );

    // 1000 milliseconds = 1.0 seconds
    expect($event->getDelayInSeconds())->toBe(1.0);
});

it('getDelayInSeconds handles fractional seconds', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        1500
    );

    // 1500 milliseconds = 1.5 seconds
    expect($event->getDelayInSeconds())->toBe(1.5);
});

it('getDelayInSeconds handles zero delay', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        0
    );

    expect($event->getDelayInSeconds())->toBe(0.0);
});

it('getDelayInSeconds handles large delays', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        60000 // 60 seconds
    );

    expect($event->getDelayInSeconds())->toBe(60.0);
});

it('can stop propagation', function () {
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        3,
        1000
    );

    expect($event->isPropagationStopped())->toBeFalse();

    $event->stopPropagation();

    expect($event->isPropagationStopped())->toBeTrue();
});

it('works with various delay values', function () {
    $delays = [100, 500, 1000, 2000, 5000, 10000];

    foreach ($delays as $delay) {
        $event = new RetryAttemptEvent(
            $this->request,
            new Exception('error'),
            2,
            3,
            $delay
        );

        expect($event->getDelay())->toBe($delay)
            ->and($event->getDelayInSeconds())->toBe($delay / 1000.0);
    }
});

it('works with first retry attempt', function () {
    // First retry is attempt number 2
    $event = new RetryAttemptEvent(
        $this->request,
        new Exception('error'),
        2,
        5,
        1000
    );

    expect($event->getAttemptNumber())->toBe(2)
        ->and($event->getRemainingAttempts())->toBe(4)
        ->and($event->isLastAttempt())->toBeFalse();
});

it('works with different max attempts', function () {
    $maxAttempts = [1, 2, 3, 5, 10];

    foreach ($maxAttempts as $max) {
        $event = new RetryAttemptEvent(
            $this->request,
            new Exception('error'),
            1,
            $max,
            1000
        );

        expect($event->getMaxAttempts())->toBe($max);
    }
});

it('handles different exception types', function () {
    $exceptions = [
        new Exception('Generic error'),
        new RuntimeException('Runtime error'),
    ];

    foreach ($exceptions as $exception) {
        $event = new RetryAttemptEvent(
            $this->request,
            $exception,
            2,
            3,
            1000
        );

        expect($event->getReason())->toBe($exception);
    }
});
