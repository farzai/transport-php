<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

/**
 * Base interface for all transport events.
 *
 * Design Pattern: Observer Pattern
 * - Events represent things that have happened in the system
 * - Listeners can observe events without modifying behavior
 * - Supports event propagation control
 *
 * Inspired by PSR-14 Event Dispatcher but simplified for our use case.
 *
 * @example
 * ```php
 * $transport->addEventListener(ResponseReceivedEvent::class, function ($event) {
 *     if ($event->getResponse()->statusCode() >= 500) {
 *         $this->logger->error('Server error', ['event' => $event]);
 *         $event->stopPropagation();
 *     }
 * });
 * ```
 */
interface EventInterface
{
    /**
     * Check if event propagation has been stopped.
     *
     * When true, the event dispatcher will not call any remaining listeners.
     *
     * @return bool True if propagation stopped, false otherwise
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop event propagation.
     *
     * This prevents subsequent listeners from being called for this event.
     * Useful for error handling or circuit breaking.
     */
    public function stopPropagation(): void;
}
