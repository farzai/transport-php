<?php

/**
 * Custom HTTP Client Example
 *
 * This example shows how to use different HTTP client implementations
 * (Guzzle, Symfony) with Transport PHP.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Factory\ClientFactory;
use Farzai\Transport\TransportBuilder;

echo "=== Custom HTTP Client Examples ===\n\n";

// Example 1: Auto-detection
echo "1. Auto-detecting HTTP Client\n";
echo str_repeat('-', 50)."\n";

try {
    $clientName = ClientFactory::getDetectedClientName();
    echo "Auto-detected client: {$clientName}\n\n";

    $transport = TransportBuilder::make()
        ->withBaseUri('https://jsonplaceholder.typicode.com')
        ->build();

    $response = $transport->get('/posts/1')->send();
    echo "Successfully fetched post using {$clientName}\n";
    echo "Title: {$response->json('title')}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 2: Explicitly using Guzzle (if available)
echo "2. Using Guzzle HTTP Client\n";
echo str_repeat('-', 50)."\n";

if (ClientFactory::isAvailable('guzzle')) {
    try {
        $guzzleClient = ClientFactory::createGuzzle([
            'timeout' => 30,
            'verify' => true,
        ]);

        $transport = TransportBuilder::make()
            ->setClient($guzzleClient)
            ->withBaseUri('https://jsonplaceholder.typicode.com')
            ->build();

        $response = $transport->get('/posts/1')->send();
        echo "Successfully fetched post using Guzzle\n";
        echo "Title: {$response->json('title')}\n\n";
    } catch (\Exception $e) {
        echo "Error: {$e->getMessage()}\n\n";
    }
} else {
    echo "Guzzle is not installed\n";
    echo "Install it with: composer require guzzlehttp/guzzle\n\n";
}

// Example 3: Explicitly using Symfony HTTP Client (if available)
echo "3. Using Symfony HTTP Client\n";
echo str_repeat('-', 50)."\n";

if (ClientFactory::isAvailable('symfony')) {
    try {
        $symfonyClient = ClientFactory::createSymfony([
            'timeout' => 30,
            'max_redirects' => 5,
        ]);

        $transport = TransportBuilder::make()
            ->setClient($symfonyClient)
            ->withBaseUri('https://jsonplaceholder.typicode.com')
            ->build();

        $response = $transport->get('/posts/1')->send();
        echo "Successfully fetched post using Symfony HTTP Client\n";
        echo "Title: {$response->json('title')}\n\n";
    } catch (\Exception $e) {
        echo "Error: {$e->getMessage()}\n\n";
    }
} else {
    echo "Symfony HTTP Client is not installed\n";
    echo "Install it with: composer require symfony/http-client\n\n";
}

// Example 4: Checking Available Clients
echo "4. Checking Available HTTP Clients\n";
echo str_repeat('-', 50)."\n";

$clients = [
    'guzzle' => 'GuzzleHTTP\\Client',
    'symfony' => 'Symfony\\Component\\HttpClient\\Psr18Client',
];

echo "Available HTTP clients:\n";
foreach ($clients as $name => $class) {
    $available = ClientFactory::isAvailable($name);
    $status = $available ? '✓ Available' : '✗ Not installed';
    echo "  {$name}: {$status}\n";

    if (! $available) {
        echo "    Install with: composer require ";
        echo $name === 'guzzle' ? 'guzzlehttp/guzzle' : 'symfony/http-client';
        echo "\n";
    }
}
echo "\n";

// Example 5: Using with Logger
echo "5. HTTP Client with Logging\n";
echo str_repeat('-', 50)."\n";

try {
    // Create a simple logger
    $logger = new class extends \Psr\Log\AbstractLogger {
        public function log($level, $message, array $context = []): void
        {
            $contextStr = ! empty($context) ? ' '.json_encode($context) : '';
            echo "[{$level}] {$message}{$contextStr}\n";
        }
    };

    // Create client with logger (logs which client was detected)
    $client = ClientFactory::create($logger);

    $transport = TransportBuilder::make()
        ->setClient($client)
        ->setLogger($logger)
        ->withBaseUri('https://jsonplaceholder.typicode.com')
        ->build();

    $response = $transport->get('/posts/1')->send();
    echo "\nTitle: {$response->json('title')}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

echo "=== Examples Complete ===\n";
