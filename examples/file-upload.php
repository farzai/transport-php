<?php

/**
 * File Upload Example
 *
 * This example demonstrates how to upload files using multipart/form-data
 * with the Transport PHP library.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Multipart\MultipartStreamBuilder;
use Farzai\Transport\TransportBuilder;

// Create a transport instance
$transport = TransportBuilder::make()
    ->withBaseUri('https://httpbin.org')
    ->withTimeout(60) // Longer timeout for file uploads
    ->build();

echo "=== Transport PHP File Upload Examples ===\n\n";

// Example 1: Simple file upload using withFile()
echo "1. Simple File Upload\n";
echo str_repeat('-', 50)."\n";

try {
    // Create a temporary test file
    $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
    file_put_contents($tempFile, 'This is a test file content for upload example.');

    $response = $transport->post('/post')
        ->withFile(
            name: 'document',
            path: $tempFile,
            filename: 'test-document.txt',
            additionalFields: [
                'description' => 'Test upload',
                'category' => 'documents',
            ]
        )
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Upload successful!\n";

    // Clean up
    unlink($tempFile);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 2: Multiple file upload with mixed fields
echo "2. Multiple Files with Form Fields\n";
echo str_repeat('-', 50)."\n";

try {
    $response = $transport->post('/post')
        ->withMultipart([
            // Text fields
            ['name' => 'username', 'contents' => 'john_doe'],
            ['name' => 'email', 'contents' => 'john@example.com'],

            // File from contents (string)
            [
                'name' => 'avatar',
                'contents' => 'fake-image-data',
                'filename' => 'avatar.jpg',
                'content-type' => 'image/jpeg',
            ],

            // Another file
            [
                'name' => 'document',
                'contents' => 'PDF content here',
                'filename' => 'report.pdf',
                'content-type' => 'application/pdf',
            ],
        ])
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Multiple files uploaded successfully!\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 3: Upload with simple key-value format
echo "3. Simple Format with Text and Files\n";
echo str_repeat('-', 50)."\n";

try {
    // Create temporary files
    $file1 = tempnam(sys_get_temp_dir(), 'doc1_');
    $file2 = tempnam(sys_get_temp_dir(), 'doc2_');
    file_put_contents($file1, 'Content of first document');
    file_put_contents($file2, 'Content of second document');

    $response = $transport->post('/post')
        ->withMultipart([
            // Simple text fields (key => value)
            'title' => 'My Documents',
            'description' => 'Important files',

            // File uploads (array format)
            [
                'name' => 'file1',
                'contents' => file_get_contents($file1),
                'filename' => 'document1.txt',
            ],
            [
                'name' => 'file2',
                'contents' => file_get_contents($file2),
                'filename' => 'document2.txt',
            ],
        ])
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Documents uploaded with metadata!\n";

    // Clean up
    unlink($file1);
    unlink($file2);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 4: Advanced - Using MultipartStreamBuilder directly
echo "4. Advanced: Using MultipartStreamBuilder\n";
echo str_repeat('-', 50)."\n";

try {
    // Create builder
    $builder = new MultipartStreamBuilder;

    // Add various fields
    $builder->addField('user_id', '12345')
        ->addField('action', 'upload')
        ->addFileContents(
            name: 'profile_picture',
            contents: 'image-binary-data-here',
            filename: 'profile.jpg',
            contentType: 'image/jpeg'
        );

    // Create temporary file for another upload
    $docFile = tempnam(sys_get_temp_dir(), 'contract_');
    file_put_contents($docFile, 'Contract document content');

    $builder->addFile(
        name: 'contract',
        path: $docFile,
        filename: 'employment-contract.pdf',
        contentType: 'application/pdf'
    );

    // Use the builder
    $response = $transport->post('/post')
        ->withMultipartBuilder($builder)
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Builder boundary: {$builder->getBoundary()}\n";
    echo "Total parts: {$builder->count()}\n";

    // Clean up
    unlink($docFile);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 5: Image upload with proper content type
echo "5. Image Upload with Content Type\n";
echo str_repeat('-', 50)."\n";

try {
    // Simulate image data
    $imageData = 'FAKE_PNG_DATA_'.bin2hex(random_bytes(10));

    $response = $transport->post('/post')
        ->withMultipart([
            ['name' => 'title', 'contents' => 'My Vacation Photo'],
            ['name' => 'tags', 'contents' => 'vacation,beach,2024'],
            [
                'name' => 'photo',
                'contents' => $imageData,
                'filename' => 'vacation.png',
                'content-type' => 'image/png',
            ],
        ])
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Image uploaded with metadata!\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 6: Custom boundary
echo "6. Custom Boundary\n";
echo str_repeat('-', 50)."\n";

try {
    $customBoundary = 'MyCustomBoundary123456789';

    $response = $transport->post('/post')
        ->withMultipart(
            data: [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
            boundary: $customBoundary
        )
        ->send();

    echo "Status: {$response->statusCode()}\n";
    echo "Upload with custom boundary: {$customBoundary}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 7: Large file simulation with progress indication
echo "7. Large File Upload\n";
echo str_repeat('-', 50)."\n";

try {
    // Create a larger test file
    $largeFile = tempnam(sys_get_temp_dir(), 'large_');
    $content = str_repeat('This is a large file content. ', 1000);
    file_put_contents($largeFile, $content);

    echo 'Uploading file ('.round(strlen($content) / 1024, 2)." KB)...\n";

    $startTime = microtime(true);

    $response = $transport->post('/post')
        ->withFile('large_file', $largeFile, 'large-document.txt')
        ->send();

    $duration = round(microtime(true) - $startTime, 2);

    echo "Status: {$response->statusCode()}\n";
    echo "Upload completed in {$duration} seconds\n";

    // Clean up
    unlink($largeFile);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

echo "=== Examples Complete ===\n";
echo "\nNote: These examples use httpbin.org which echoes back the received data.\n";
echo "In production, replace the URL with your actual API endpoint.\n";
