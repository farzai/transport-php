<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

/**
 * Event dispatcher interface for managing event listeners and dispatching events.
 *
 * Design Pattern: Observer Pattern (Subject)
 * - Maintains list of observers (listeners)
 * - Notifies observers when events occur
 * - Allows dynamic listener registration/removal
 *
 * Simplified from PSR-14 for Transport use cases.
 *
 * @example
 * ```php
 * $dispatcher = new EventDispatcher();
 *
 * // Add listener
 * $dispatcher->addEventListener(RequestSentEvent::class, function ($event) {
 *     echo "Request sent to: " . $event->getRequest()->getUri() . "\n";
 * });
 *
 * // Dispatch event
 * $dispatcher->dispatch(new RequestSentEvent($request, $response));
 * ```
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * Listeners are called in the order they were registered.
     * If a listener calls stopPropagation() on the event,
     * remaining listeners will not be called.
     *
     * @param  EventInterface  $event  The event to dispatch
     */
    public function dispatch(EventInterface $event): void;

    /**
     * Add an event listener for a specific event class.
     *
     * The listener will be called whenever an event of the specified
     * class (or subclass) is dispatched.
     *
     * @param  class-string<EventInterface>  $eventClass  The event class to listen for
     * @param  callable(EventInterface): void  $listener  The listener callable
     */
    public function addEventListener(string $eventClass, callable $listener): void;

    /**
     * Remove an event listener.
     *
     * The listener must be the exact same callable instance that was
     * registered with addEventListener().
     *
     * @param  class-string<EventInterface>  $eventClass  The event class
     * @param  callable(EventInterface): void  $listener  The listener to remove
     */
    public function removeEventListener(string $eventClass, callable $listener): void;

    /**
     * Check if any listeners are registered for an event class.
     *
     * @param  class-string<EventInterface>  $eventClass  The event class
     * @return bool True if listeners exist, false otherwise
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Get all listeners for an event class.
     *
     * @param  class-string<EventInterface>  $eventClass  The event class
     * @return array<callable> Array of listener callables
     */
    public function getListeners(string $eventClass): array;

    /**
     * Remove all listeners for a specific event class.
     *
     * If no event class is specified, removes ALL listeners.
     *
     * @param  class-string<EventInterface>|null  $eventClass  Optional event class
     */
    public function removeAllListeners(?string $eventClass = null): void;
}
