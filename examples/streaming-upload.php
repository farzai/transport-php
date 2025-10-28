<?php

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Multipart\MultipartBuilderFactory;
use Farzai\Transport\Multipart\StreamingMultipartBuilder;
use Farzai\Transport\TransportBuilder;

echo "Streaming Upload Example\n";
echo "========================\n\n";

// Create a test file
$testFile = sys_get_temp_dir().'/test-upload.txt';
file_put_contents($testFile, str_repeat('Large file content... ', 1000));
$fileSize = filesize($testFile);

echo "Test file: {$testFile}\n";
echo 'File size: '.number_format($fileSize)." bytes\n\n";

// Method 1: Manual streaming builder
echo "Method 1: Manual Streaming Builder\n";
echo "-----------------------------------\n";

$transport = TransportBuilder::make()
    ->withBaseUri('https://httpbin.org')
    ->build();

$builder = new StreamingMultipartBuilder;
$stream = $builder
    ->addFile('file', $testFile, 'upload.txt', 'text/plain')
    ->addField('description', 'Streaming upload test')
    ->addField('timestamp', (string) time())
    ->build();

echo 'Memory before: '.number_format(memory_get_usage(true) / 1024)." KB\n";

$response = $transport->post('/post')
    ->withBody($stream)
    ->withHeader('Content-Type', $builder->getContentType())
    ->send();

echo 'Memory after:  '.number_format(memory_get_usage(true) / 1024)." KB\n";
echo 'Status: '.$response->statusCode()."\n\n";

// Method 2: Auto-selection with factory
echo "Method 2: Auto-Selection Factory\n";
echo "---------------------------------\n";

$parts = [
    ['name' => 'file', 'contents' => $testFile, 'filename' => 'upload.txt'],
    ['name' => 'description', 'contents' => 'Auto-selected builder'],
];

$builder = MultipartBuilderFactory::create(null, $parts);
echo 'Selected builder: '.($builder instanceof StreamingMultipartBuilder ? 'Streaming' : 'Standard')."\n";
echo 'Threshold: '.MultipartBuilderFactory::getDefaultThreshold()." bytes\n\n";

// Cleanup
unlink($testFile);

echo "Example complete!\n";
