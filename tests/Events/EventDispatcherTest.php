<?php

use Farzai\Transport\Events\AbstractEvent;
use Farzai\Transport\Events\EventDispatcher;
use Farzai\Transport\Events\EventInterface;

beforeEach(function () {
    $this->dispatcher = new EventDispatcher;
});

it('can add single listener for event', function () {
    $called = false;
    $listener = function (EventInterface $event) use (&$called) {
        $called = true;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeTrue();
    expect($this->dispatcher->getListeners(TestEvent::class))->toHaveCount(1);
});

it('can add multiple listeners for same event', function () {
    $listener1 = fn ($event) => null;
    $listener2 = fn ($event) => null;
    $listener3 = fn ($event) => null;

    $this->dispatcher->addEventListener(TestEvent::class, $listener1);
    $this->dispatcher->addEventListener(TestEvent::class, $listener2);
    $this->dispatcher->addEventListener(TestEvent::class, $listener3);

    expect($this->dispatcher->getListeners(TestEvent::class))->toHaveCount(3);
});

it('listeners receive event object when dispatched', function () {
    $receivedEvent = null;
    $listener = function (EventInterface $event) use (&$receivedEvent) {
        $receivedEvent = $event;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener);

    $event = new TestEvent('test-data');
    $this->dispatcher->dispatch($event);

    expect($receivedEvent)->toBe($event);
    expect($receivedEvent->getData())->toBe('test-data');
});

it('can remove specific listener', function () {
    $listener1 = fn ($event) => null;
    $listener2 = fn ($event) => null;

    $this->dispatcher->addEventListener(TestEvent::class, $listener1);
    $this->dispatcher->addEventListener(TestEvent::class, $listener2);

    expect($this->dispatcher->getListeners(TestEvent::class))->toHaveCount(2);

    $this->dispatcher->removeEventListener(TestEvent::class, $listener1);

    expect($this->dispatcher->getListeners(TestEvent::class))->toHaveCount(1);
    expect(array_values($this->dispatcher->getListeners(TestEvent::class)))->toBe([$listener2]);
});

it('can remove all listeners for event type', function () {
    $this->dispatcher->addEventListener(TestEvent::class, fn ($e) => null);
    $this->dispatcher->addEventListener(TestEvent::class, fn ($e) => null);
    $this->dispatcher->addEventListener(AnotherTestEvent::class, fn ($e) => null);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeTrue();
    expect($this->dispatcher->hasListeners(AnotherTestEvent::class))->toBeTrue();

    $this->dispatcher->removeAllListeners(TestEvent::class);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeFalse();
    expect($this->dispatcher->hasListeners(AnotherTestEvent::class))->toBeTrue();
});

it('can remove all listeners without event class', function () {
    $this->dispatcher->addEventListener(TestEvent::class, fn ($e) => null);
    $this->dispatcher->addEventListener(AnotherTestEvent::class, fn ($e) => null);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeTrue();
    expect($this->dispatcher->hasListeners(AnotherTestEvent::class))->toBeTrue();

    $this->dispatcher->removeAllListeners();

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeFalse();
    expect($this->dispatcher->hasListeners(AnotherTestEvent::class))->toBeFalse();
});

it('hasListeners returns correct boolean', function () {
    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeFalse();

    $this->dispatcher->addEventListener(TestEvent::class, fn ($e) => null);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeTrue();
});

it('getListeners returns empty array for unregistered event', function () {
    expect($this->dispatcher->getListeners(TestEvent::class))->toBe([]);
});

it('stops calling listeners when propagation stopped', function () {
    $callCount = 0;

    $listener1 = function (EventInterface $event) use (&$callCount) {
        $callCount++;
        $event->stopPropagation();
    };

    $listener2 = function (EventInterface $event) use (&$callCount) {
        $callCount++;
    };

    $listener3 = function (EventInterface $event) use (&$callCount) {
        $callCount++;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener1);
    $this->dispatcher->addEventListener(TestEvent::class, $listener2);
    $this->dispatcher->addEventListener(TestEvent::class, $listener3);

    $event = new TestEvent('test');
    $this->dispatcher->dispatch($event);

    expect($callCount)->toBe(1);
    expect($event->isPropagationStopped())->toBeTrue();
});

it('calls listeners for parent event classes', function () {
    $baseListenerCalled = false;
    $childListenerCalled = false;

    $baseListener = function (EventInterface $event) use (&$baseListenerCalled) {
        $baseListenerCalled = true;
    };

    $childListener = function (EventInterface $event) use (&$childListenerCalled) {
        $childListenerCalled = true;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $baseListener);
    $this->dispatcher->addEventListener(ChildTestEvent::class, $childListener);

    $event = new ChildTestEvent('child-data');
    $this->dispatcher->dispatch($event);

    expect($baseListenerCalled)->toBeTrue();
    expect($childListenerCalled)->toBeTrue();
});

it('listener removal does not affect other listeners', function () {
    $listener1Called = false;
    $listener2Called = false;

    $listener1 = function ($event) use (&$listener1Called) {
        $listener1Called = true;
    };

    $listener2 = function ($event) use (&$listener2Called) {
        $listener2Called = true;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener1);
    $this->dispatcher->addEventListener(TestEvent::class, $listener2);

    $this->dispatcher->removeEventListener(TestEvent::class, $listener1);
    $this->dispatcher->dispatch(new TestEvent('test'));

    expect($listener1Called)->toBeFalse();
    expect($listener2Called)->toBeTrue();
});

it('cleans up empty listener arrays after removal', function () {
    $listener = fn ($e) => null;

    $this->dispatcher->addEventListener(TestEvent::class, $listener);
    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeTrue();

    $this->dispatcher->removeEventListener(TestEvent::class, $listener);
    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeFalse();
    expect($this->dispatcher->getListeners(TestEvent::class))->toBe([]);
});

it('handles removing non existent listener gracefully', function () {
    $listener = fn ($e) => null;

    $this->dispatcher->removeEventListener(TestEvent::class, $listener);

    expect($this->dispatcher->hasListeners(TestEvent::class))->toBeFalse();
});

it('dispatches events in order of registration', function () {
    $order = [];

    $listener1 = function ($event) use (&$order) {
        $order[] = 1;
    };

    $listener2 = function ($event) use (&$order) {
        $order[] = 2;
    };

    $listener3 = function ($event) use (&$order) {
        $order[] = 3;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener1);
    $this->dispatcher->addEventListener(TestEvent::class, $listener2);
    $this->dispatcher->addEventListener(TestEvent::class, $listener3);

    $this->dispatcher->dispatch(new TestEvent('test'));

    expect($order)->toBe([1, 2, 3]);
});

it('allows same listener to be registered multiple times', function () {
    $callCount = 0;
    $listener = function ($event) use (&$callCount) {
        $callCount++;
    };

    $this->dispatcher->addEventListener(TestEvent::class, $listener);
    $this->dispatcher->addEventListener(TestEvent::class, $listener);

    $this->dispatcher->dispatch(new TestEvent('test'));

    expect($callCount)->toBe(2);
});

class TestEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $data
    ) {}

    public function getData(): string
    {
        return $this->data;
    }
}

class AnotherTestEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $data = ''
    ) {}
}

class ChildTestEvent extends TestEvent {}
