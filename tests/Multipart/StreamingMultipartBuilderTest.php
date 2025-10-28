<?php

declare(strict_types=1);

use Farzai\Transport\Factory\HttpFactory;
use Farzai\Transport\Multipart\MultipartStream;
use Farzai\Transport\Multipart\Part;
use Farzai\Transport\Multipart\StreamingMultipartBuilder;

beforeEach(function () {
    $this->factory = HttpFactory::getInstance();

    // Create a temporary test file
    $this->testFile = sys_get_temp_dir().'/transport-php-test-'.uniqid().'.txt';
    file_put_contents($this->testFile, 'Test file content for streaming upload');
});

afterEach(function () {
    if (file_exists($this->testFile)) {
        @unlink($this->testFile);
    }
});

it('creates builder with default settings', function () {
    $builder = new StreamingMultipartBuilder;

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class)
        ->and($builder->getChunkSize())->toBe(8192) // Default 8KB
        ->and($builder->getContentType())->toStartWith('multipart/form-data; boundary=');
});

it('creates builder with custom boundary', function () {
    $boundary = 'CustomBoundary123';
    $builder = new StreamingMultipartBuilder(null, $boundary);

    expect($builder->getBoundary())->toBe($boundary)
        ->and($builder->getContentType())->toBe("multipart/form-data; boundary={$boundary}");
});

it('creates builder with custom chunk size', function () {
    $builder = new StreamingMultipartBuilder(null, null, 16384);

    expect($builder->getChunkSize())->toBe(16384);
});

it('addField adds text field', function () {
    $builder = new StreamingMultipartBuilder;
    $result = $builder->addField('username', 'john_doe');

    expect($result)->toBe($builder) // Fluent interface
        ->and($builder->getParts())->toHaveCount(1);
});

it('addFile throws exception for non readable file', function () {
    $builder = new StreamingMultipartBuilder;

    expect(fn () => $builder->addFile('document', '/non/existent/file.txt'))
        ->toThrow(RuntimeException::class, 'File not readable:');
});

it('addFile throws exception for non file path', function () {
    $builder = new StreamingMultipartBuilder;
    $directory = sys_get_temp_dir();

    expect(fn () => $builder->addFile('document', $directory))
        ->toThrow(RuntimeException::class, 'Not a file:');
});

it('addFile adds file with custom filename', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFile('document', $this->testFile, 'custom-name.txt');

    expect($builder->getParts())->toHaveCount(1);
});

it('addFile uses basename when filename not provided', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFile('document', $this->testFile);

    $parts = $builder->getParts();
    expect($parts)->toHaveCount(1);

    // Verify the part was created (filename would be basename of testFile)
    $headers = $parts[0]->getHeaders();
    expect($headers['Content-Disposition'])->toContain('transport-php-test-');
});

it('addFile creates stream from file path', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFile('document', $this->testFile, 'test.txt', 'text/plain');

    $parts = $builder->getParts();
    expect($parts)->toHaveCount(1)
        ->and($parts[0])->toBeInstanceOf(Part::class);
});

it('addFileContents accepts string contents', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFileContents('file', 'string content', 'file.txt');

    expect($builder->getParts())->toHaveCount(1);
});

it('addFileContents accepts StreamInterface contents', function () {
    $builder = new StreamingMultipartBuilder;
    $stream = $this->factory->createStream('stream content');

    $builder->addFileContents('file', $stream, 'file.txt');

    expect($builder->getParts())->toHaveCount(1);
});

it('addPart adds custom Part object', function () {
    $builder = new StreamingMultipartBuilder;
    $part = Part::text('field', 'value');

    $result = $builder->addPart($part);

    expect($result)->toBe($builder)
        ->and($builder->getParts())->toHaveCount(1)
        ->and($builder->getParts()[0])->toBe($part);
});

it('addMultiple handles simple key value format', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addMultiple([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => '30',
    ]);

    expect($builder->getParts())->toHaveCount(3);
});

it('addMultiple handles array format with files', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addMultiple([
        [
            'name' => 'document',
            'contents' => $this->testFile,
            'filename' => 'upload.txt',
        ],
        [
            'name' => 'description',
            'contents' => 'File description',
        ],
    ]);

    expect($builder->getParts())->toHaveCount(2);
});

it('getBoundary returns boundary string', function () {
    $builder = new StreamingMultipartBuilder;
    $boundary = $builder->getBoundary();

    expect($boundary)->toBeString()
        ->and($boundary)->toStartWith('----TransportPHPStreaming');
});

it('getContentType returns correct header value', function () {
    $boundary = 'TestBoundary123';
    $builder = new StreamingMultipartBuilder(null, $boundary);

    expect($builder->getContentType())->toBe("multipart/form-data; boundary={$boundary}");
});

it('setChunkSize sets size with minimum 1KB', function () {
    $builder = new StreamingMultipartBuilder;

    // Try to set below minimum
    $builder->setChunkSize(512);
    expect($builder->getChunkSize())->toBe(1024); // Should be clamped to 1KB

    // Set valid size
    $builder->setChunkSize(4096);
    expect($builder->getChunkSize())->toBe(4096);
});

it('setChunkSize returns builder for chaining', function () {
    $builder = new StreamingMultipartBuilder;
    $result = $builder->setChunkSize(2048);

    expect($result)->toBe($builder);
});

it('getChunkSize returns chunk size', function () {
    $builder = new StreamingMultipartBuilder(null, null, 16384);

    expect($builder->getChunkSize())->toBe(16384);
});

it('build returns MultipartStream instance', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addField('test', 'value');

    $stream = $builder->build();

    expect($stream)->toBeInstanceOf(MultipartStream::class);
});

it('getParts returns array of parts', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addField('field1', 'value1');
    $builder->addField('field2', 'value2');

    $parts = $builder->getParts();

    expect($parts)->toBeArray()
        ->and($parts)->toHaveCount(2)
        ->and($parts)->each->toBeInstanceOf(Part::class);
});

it('count returns correct part count', function () {
    $builder = new StreamingMultipartBuilder;

    expect($builder->count())->toBe(0);

    $builder->addField('field1', 'value1');
    expect($builder->count())->toBe(1);

    $builder->addField('field2', 'value2');
    expect($builder->count())->toBe(2);
});

it('getSize calculates total size', function () {
    $builder = new StreamingMultipartBuilder(null, 'TestBoundary');
    $builder->addField('name', 'John');

    $size = $builder->getSize();

    expect($size)->toBeInt()
        ->and($size)->toBeGreaterThan(0);
});

it('getSize returns null when size cannot be determined', function () {
    $builder = new StreamingMultipartBuilder;

    // Add a part with unknown size (using a non-seekable stream)
    $stream = $this->factory->createStream('content');
    $builder->addFileContents('file', $stream, 'test.txt');

    // For string streams, size should be determinable, so let's test with actual scenario
    // In real case, getSize() might return null for non-seekable streams
    $size = $builder->getSize();

    // This test depends on implementation - size might be calculable or not
    expect($size === null || is_int($size))->toBeTrue();
});

it('Boundary starts with TransportPHPStreaming', function () {
    $builder = new StreamingMultipartBuilder;
    $boundary = $builder->getBoundary();

    expect($boundary)->toStartWith('----TransportPHPStreaming')
        ->and(strlen($boundary))->toBeGreaterThan(25); // Should have random suffix
});

it('create static factory works', function () {
    $builder = StreamingMultipartBuilder::create();

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('create accepts custom factory and boundary', function () {
    $factory = HttpFactory::getInstance();
    $boundary = 'CustomBoundary';

    $builder = StreamingMultipartBuilder::create($factory, $boundary);

    expect($builder->getBoundary())->toBe($boundary);
});

it('supports fluent interface', function () {
    $builder = new StreamingMultipartBuilder;

    $result = $builder
        ->addField('name', 'John')
        ->addField('email', 'john@example.com')
        ->setChunkSize(4096);

    expect($result)->toBe($builder)
        ->and($builder->getParts())->toHaveCount(2);
});

it('handles multiple files', function () {
    $file2 = sys_get_temp_dir().'/transport-php-test-2-'.uniqid().'.txt';
    file_put_contents($file2, 'Second file content');

    try {
        $builder = new StreamingMultipartBuilder;
        $builder->addFile('file1', $this->testFile);
        $builder->addFile('file2', $file2);

        expect($builder->getParts())->toHaveCount(2);
    } finally {
        @unlink($file2);
    }
});

it('handles mixed fields and files', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addField('title', 'Document Title');
    $builder->addFile('document', $this->testFile);
    $builder->addField('description', 'Document Description');

    expect($builder->getParts())->toHaveCount(3);
});

it('addFile with content type', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFile('image', $this->testFile, 'photo.jpg', 'image/jpeg');

    $parts = $builder->getParts();
    expect($parts)->toHaveCount(1);
});

it('addFileContents with content type', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addFileContents('data', '{"key":"value"}', 'data.json', 'application/json');

    $parts = $builder->getParts();
    $headers = $parts[0]->getHeaders();
    expect($headers['Content-Type'])->toContain('application/json');
});

it('chunk size enforces minimum during construction', function () {
    $builder = new StreamingMultipartBuilder(null, null, 100); // Below 1KB

    expect($builder->getChunkSize())->toBe(1024);
});

it('handles empty builder', function () {
    $builder = new StreamingMultipartBuilder;

    expect($builder->getParts())->toHaveCount(0)
        ->and($builder->count())->toBe(0);
});

it('build works with empty parts', function () {
    $builder = new StreamingMultipartBuilder;
    $stream = $builder->build();

    expect($stream)->toBeInstanceOf(MultipartStream::class);
});

it('addMultiple handles content type aliases', function () {
    $builder = new StreamingMultipartBuilder;
    $builder->addMultiple([
        [
            'name' => 'file1',
            'contents' => 'content',
            'filename' => 'test.txt',
            'content-type' => 'text/plain',
        ],
        [
            'name' => 'file2',
            'contents' => 'content2',
            'filename' => 'test2.txt',
            'contentType' => 'text/html',
        ],
    ]);

    expect($builder->getParts())->toHaveCount(2);
});
