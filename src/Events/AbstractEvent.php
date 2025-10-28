<?php

declare(strict_types=1);

namespace Farzai\Transport\Events;

/**
 * Abstract base class for all events providing common functionality.
 *
 * Implements propagation control which is common to all events.
 * Concrete event classes should extend this and add specific data/methods.
 *
 * Design Pattern: Template Method Pattern
 * - Provides default implementation of event interface
 * - Subclasses add specific event data and behavior
 *
 * @example
 * ```php
 * class CustomEvent extends AbstractEvent
 * {
 *     public function __construct(
 *         private readonly mixed $data
 *     ) {}
 *
 *     public function getData(): mixed
 *     {
 *         return $this->data;
 *     }
 * }
 * ```
 */
abstract class AbstractEvent implements EventInterface
{
    /**
     * Whether event propagation has been stopped.
     */
    private bool $propagationStopped = false;

    /**
     * {@inheritDoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * {@inheritDoc}
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
