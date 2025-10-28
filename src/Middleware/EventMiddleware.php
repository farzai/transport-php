<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Farzai\Transport\Events\EventDispatcherInterface;
use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Events\RequestSendingEvent;
use Farzai\Transport\Events\RequestSentEvent;
use Farzai\Transport\Events\ResponseReceivedEvent;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that dispatches events during the HTTP lifecycle.
 *
 * Design Pattern: Observer Pattern + Middleware Pattern
 * - Observes HTTP lifecycle through middleware pipeline
 * - Dispatches events to registered listeners
 * - Non-invasive (doesn't modify request/response)
 *
 * Events Dispatched:
 * 1. RequestSendingEvent - Before request enters pipeline
 * 2. RequestSentEvent - Immediately after HTTP client returns
 * 3. ResponseReceivedEvent - After middleware unwinding completes
 * 4. RequestFailedEvent - When request fails
 *
 * @example
 * ```php
 * $dispatcher = new EventDispatcher();
 * $dispatcher->addEventListener(ResponseReceivedEvent::class, function ($event) {
 *     $this->metrics->recordLatency($event->getDuration());
 * });
 *
 * $transport = TransportBuilder::make()
 *     ->withMiddleware(new EventMiddleware($dispatcher))
 *     ->build();
 * ```
 */
final class EventMiddleware implements MiddlewareInterface
{
    /**
     * Create a new event middleware.
     *
     * @param  EventDispatcherInterface  $dispatcher  The event dispatcher
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $startTime = microtime(true);

        // Dispatch RequestSendingEvent
        $this->dispatcher->dispatch(
            new RequestSendingEvent($request, $startTime)
        );

        try {
            // Call next middleware / HTTP client
            $response = $next($request);

            $sentTime = microtime(true);
            $sentDuration = ($sentTime - $startTime) * 1000; // Convert to milliseconds

            // Dispatch RequestSentEvent - immediately after HTTP client returns
            $this->dispatcher->dispatch(
                new RequestSentEvent($request, $response, $sentDuration, $sentTime)
            );

            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000; // Total duration including middleware

            // Dispatch ResponseReceivedEvent - after all processing
            $this->dispatcher->dispatch(
                new ResponseReceivedEvent($request, $response, $duration, $endTime)
            );

            return $response;
        } catch (\Throwable $exception) {
            $failureTime = microtime(true);

            // Dispatch RequestFailedEvent
            $this->dispatcher->dispatch(
                new RequestFailedEvent($request, $exception, 1, $failureTime)
            );

            // Re-throw the exception
            throw $exception;
        }
    }

    /**
     * Get the event dispatcher.
     *
     * Useful for tests or runtime inspection.
     *
     * @return EventDispatcherInterface The event dispatcher
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }
}
