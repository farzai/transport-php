<?php

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Events\RequestFailedEvent;
use Farzai\Transport\Events\ResponseReceivedEvent;
use Farzai\Transport\Events\RetryAttemptEvent;
use Farzai\Transport\TransportBuilder;

echo "Event Monitoring Example\n";
echo "========================\n\n";

// Create transport with event listeners
$transport = TransportBuilder::make()
    ->withBaseUri('https://httpbin.org')
    ->addEventListener(ResponseReceivedEvent::class, function ($event) {
        printf(
            "[SUCCESS] %s %s → %d (%.2fms)\n",
            $event->getMethod(),
            $event->getUri(),
            $event->getStatusCode(),
            $event->getDuration()
        );
    })
    ->addEventListener(RequestFailedEvent::class, function ($event) {
        printf(
            "[ERROR] %s %s failed: %s\n",
            $event->getMethod(),
            $event->getUri(),
            $event->getExceptionMessage()
        );
    })
    ->addEventListener(RetryAttemptEvent::class, function ($event) {
        printf(
            "[RETRY] Attempt %d/%d (delay: %dms)\n",
            $event->getAttemptNumber(),
            $event->getMaxAttempts(),
            $event->getDelay()
        );
    })
    ->withRetries(3)
    ->build();

// Make request
echo "Making GET request...\n";
$response = $transport->get('/get?foo=bar')->send();

echo "\nResponse Status: ".$response->statusCode()."\n";
echo "Response successful!\n";
