<?php

/**
 * Basic Usage Example
 *
 * This example demonstrates the basic usage of Transport PHP library.
 * It shows how to make simple HTTP requests and handle responses.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\TransportBuilder;

// Create a transport instance with basic configuration
$transport = TransportBuilder::make()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withHeaders([
        'Accept' => 'application/json',
        'User-Agent' => 'Transport-PHP-Example/1.0',
    ])
    ->withTimeout(30)
    ->build();

echo "=== Transport PHP Basic Usage Examples ===\n\n";

// Example 1: Simple GET Request
echo "1. Simple GET Request\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->get('/posts/1')->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Title: {$response->json('title')}\n";
    echo "Body: {$response->json('body')}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 2: GET Request with Query Parameters
echo "2. GET Request with Query Parameters\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->get('/posts')
        ->withQuery([
            'userId' => 1,
            '_limit' => 3,
        ])
        ->send();

    $posts = $response->json();
    echo 'Found '.($posts ? count($posts) : 0)." posts:\n";

    foreach ($posts as $post) {
        echo "  - [{$post['id']}] {$post['title']}\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 3: POST Request with JSON Body
echo "3. POST Request with JSON Body\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->post('/posts')
        ->withJson([
            'title' => 'My New Post',
            'body' => 'This is the content of my new post.',
            'userId' => 1,
        ])
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Created Post ID: {$response->json('id')}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 4: PUT Request (Update)
echo "4. PUT Request (Update)\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->put('/posts/1')
        ->withJson([
            'id' => 1,
            'title' => 'Updated Title',
            'body' => 'Updated body content.',
            'userId' => 1,
        ])
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Updated Title: {$response->json('title')}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 5: DELETE Request
echo "5. DELETE Request\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->delete('/posts/1')->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Successfully deleted\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 6: Handling Non-2xx Responses
echo "6. Handling Non-2xx Responses\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->get('/posts/9999')->send();

    if ($response->isSuccessful()) {
        echo "Post found: {$response->json('title')}\n\n";
    } else {
        echo "Post not found (Status: {$response->statusCode()})\n\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 7: Safe JSON Parsing
echo "7. Safe JSON Parsing with jsonOrNull()\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->get('/posts/1')->send();

    // This won't throw exception even if JSON is invalid
    $data = $response->jsonOrNull();

    if ($data !== null) {
        echo "Post title: {$data['title']}\n\n";
    } else {
        echo "Invalid JSON response\n\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 8: Working with Response Headers
echo "8. Working with Response Headers\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->get('/posts/1')->send();

    echo 'Content-Type: '.implode(', ', $response->getHeader('Content-Type'))."\n";
    echo "All headers:\n";

    foreach ($response->headers() as $name => $values) {
        echo "  {$name}: ".implode(', ', $values)."\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

echo "=== Examples Complete ===\n";
