<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

/**
 * Default event dispatcher implementation.
 *
 * Design Pattern: Observer Pattern
 * - Maintains map of event classes to listener arrays
 * - Dispatches events to registered listeners
 * - Supports propagation control
 *
 * Thread Safety: Not thread-safe by default.
 * For multi-threaded environments, wrap in mutex or use
 * separate dispatcher instances per thread.
 *
 * @example
 * ```php
 * $dispatcher = new EventDispatcher();
 *
 * $dispatcher->addEventListener(RequestSentEvent::class, function ($event) {
 *     // Log request
 *     $this->logger->info('Request sent', [
 *         'uri' => (string) $event->getRequest()->getUri(),
 *         'method' => $event->getRequest()->getMethod(),
 *     ]);
 * });
 *
 * $dispatcher->addEventListener(ResponseReceivedEvent::class, function ($event) {
 *     // Record metrics
 *     $this->metrics->recordLatency($event->getDuration());
 *     $this->metrics->recordStatusCode($event->getResponse()->statusCode());
 * });
 *
 * // Dispatch events (done automatically by Transport)
 * $dispatcher->dispatch($event);
 * ```
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Map of event class names to arrays of listeners.
     *
     * @var array<class-string<EventInterface>, array<callable>>
     */
    private array $listeners = [];

    /**
     * {@inheritDoc}
     */
    public function dispatch(EventInterface $event): void
    {
        $eventClass = get_class($event);

        // Get listeners for this exact event class and parent classes
        $listeners = $this->getListenersForEvent($eventClass);

        foreach ($listeners as $listener) {
            // Stop if propagation has been stopped
            if ($event->isPropagationStopped()) {
                break;
            }

            // Call the listener
            $listener($event);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addEventListener(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * {@inheritDoc}
     */
    public function removeEventListener(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            return;
        }

        $this->listeners[$eventClass] = array_filter(
            $this->listeners[$eventClass],
            fn ($registered) => $registered !== $listener
        );

        // Clean up empty arrays
        if (empty($this->listeners[$eventClass])) {
            unset($this->listeners[$eventClass]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && ! empty($this->listeners[$eventClass]);
    }

    /**
     * {@inheritDoc}
     */
    public function getListeners(string $eventClass): array
    {
        return $this->listeners[$eventClass] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function removeAllListeners(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            $this->listeners = [];

            return;
        }

        unset($this->listeners[$eventClass]);
    }

    /**
     * Get all listeners for an event, including listeners for parent event classes.
     *
     * This allows listeners to register for base event classes and receive
     * notifications for all subclasses.
     *
     * @param  class-string  $eventClass  The event class
     * @return array<callable> Array of listeners
     */
    private function getListenersForEvent(string $eventClass): array
    {
        $listeners = [];

        // Add listeners for this exact class
        if (isset($this->listeners[$eventClass])) {
            $listeners = array_merge($listeners, $this->listeners[$eventClass]);
        }

        // Add listeners for parent classes
        $parentClass = get_parent_class($eventClass);
        while ($parentClass !== false) {
            if (isset($this->listeners[$parentClass])) {
                $listeners = array_merge($listeners, $this->listeners[$parentClass]);
            }
            $parentClass = get_parent_class($parentClass);
        }

        return $listeners;
    }
}
