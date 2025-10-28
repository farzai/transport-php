<?php

declare(strict_types=1);

use Farzai\Transport\Factory\HttpFactory;
use Farzai\Transport\Multipart\MultipartBuilderFactory;
use Farzai\Transport\Multipart\MultipartStreamBuilder;
use Farzai\Transport\Multipart\StreamingMultipartBuilder;

beforeEach(function () {
    $this->factory = HttpFactory::getInstance();

    // Create a small test file (1 KB)
    $this->smallFile = sys_get_temp_dir().'/small-file-'.uniqid().'.txt';
    file_put_contents($this->smallFile, str_repeat('a', 1024));

    // Create a large test file (11 MB - exceeds default 10 MB threshold)
    $this->largeFile = sys_get_temp_dir().'/large-file-'.uniqid().'.txt';
    $handle = fopen($this->largeFile, 'w');
    for ($i = 0; $i < 11; $i++) {
        fwrite($handle, str_repeat('b', 1024 * 1024)); // 1 MB at a time
    }
    fclose($handle);
});

afterEach(function () {
    if (file_exists($this->smallFile)) {
        @unlink($this->smallFile);
    }
    if (file_exists($this->largeFile)) {
        @unlink($this->largeFile);
    }
});

it('create returns MultipartStreamBuilder for small size', function () {
    $parts = [
        [
            'name' => 'small_file',
            'contents' => $this->smallFile,
            'filename' => 'small.txt',
        ],
    ];

    $builder = MultipartBuilderFactory::create(null, $parts);

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('create returns StreamingMultipartBuilder for large size', function () {
    $parts = [
        [
            'name' => 'large_file',
            'contents' => $this->largeFile,
            'filename' => 'large.txt',
        ],
    ];

    $builder = MultipartBuilderFactory::create(null, $parts);

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('create uses default threshold', function () {
    // Default is 10 MB
    expect(MultipartBuilderFactory::DEFAULT_STREAMING_THRESHOLD)->toBe(10 * 1024 * 1024);
});

it('create respects custom threshold', function () {
    $parts = [
        [
            'name' => 'file',
            'contents' => $this->smallFile, // 1 KB
            'filename' => 'file.txt',
        ],
    ];

    // Set threshold to 512 bytes - our 1KB file should use streaming
    $builder = MultipartBuilderFactory::create(null, $parts, 512);

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('create defaults to standard when no parts provided', function () {
    $builder = MultipartBuilderFactory::create();

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('create uses streaming when size cannot be determined', function () {
    // Test with invalid content type that will return null size
    $parts = [
        [
            'name' => 'unknown',
            'contents' => new stdClass, // Invalid content type
        ],
    ];

    $builder = MultipartBuilderFactory::create(null, $parts);

    // When size is unknown, should use streaming for safety
    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('createStandard returns MultipartStreamBuilder', function () {
    $builder = MultipartBuilderFactory::createStandard();

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('createStandard accepts custom boundary', function () {
    $boundary = 'CustomBoundary123';
    $builder = MultipartBuilderFactory::createStandard(null, $boundary);

    expect($builder->getBoundary())->toBe($boundary);
});

it('createStreaming returns StreamingMultipartBuilder', function () {
    $builder = MultipartBuilderFactory::createStreaming();

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('createStreaming accepts custom boundary and chunk size', function () {
    $boundary = 'StreamBoundary456';
    $chunkSize = 16384;

    $builder = MultipartBuilderFactory::createStreaming(null, $boundary, $chunkSize);

    expect($builder->getBoundary())->toBe($boundary)
        ->and($builder->getChunkSize())->toBe($chunkSize);
});

it('calculateTotalSize handles string contents', function () {
    $parts = [
        ['name' => 'field1', 'contents' => 'Hello'],
        ['name' => 'field2', 'contents' => 'World'],
    ];

    $shouldStream = MultipartBuilderFactory::shouldUseStreaming($parts, 100);

    expect($shouldStream)->toBeFalse(); // Total is 10 bytes, threshold is 100
});

it('calculateTotalSize handles file paths', function () {
    $parts = [
        [
            'name' => 'file',
            'contents' => $this->smallFile,
            'filename' => 'file.txt',
        ],
    ];

    $shouldStream = MultipartBuilderFactory::shouldUseStreaming($parts, 512);

    expect($shouldStream)->toBeTrue(); // 1KB file > 512 bytes threshold
});

it('calculateTotalSize handles StreamInterface', function () {
    $stream = $this->factory->createStream('Stream content here');

    $parts = [
        [
            'name' => 'stream',
            'contents' => $stream,
            'filename' => 'data.txt',
        ],
    ];

    // Stream has size, so should be calculable
    $builder = MultipartBuilderFactory::create(null, $parts, 100);

    // 19 bytes of content < 100 bytes threshold
    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('calculateTotalSize returns null for unknown size', function () {
    // Create parts with unknown size
    $parts = [
        [
            'name' => 'unknown',
            'contents' => new stdClass, // Invalid content type
        ],
    ];

    $shouldStream = MultipartBuilderFactory::shouldUseStreaming($parts);

    expect($shouldStream)->toBeTrue(); // Unknown size = use streaming
});

it('getRecommendedBuilder returns correct class', function () {
    $smallSize = 5 * 1024 * 1024; // 5 MB
    $largeSize = 15 * 1024 * 1024; // 15 MB
    $threshold = 10 * 1024 * 1024; // 10 MB

    expect(MultipartBuilderFactory::getRecommendedBuilder($smallSize, $threshold))
        ->toBe(MultipartStreamBuilder::class);

    expect(MultipartBuilderFactory::getRecommendedBuilder($largeSize, $threshold))
        ->toBe(StreamingMultipartBuilder::class);
});

it('shouldUseStreaming returns correct boolean', function () {
    $smallParts = [
        ['name' => 'text', 'contents' => 'Small content'],
    ];

    $largeParts = [
        [
            'name' => 'file',
            'contents' => $this->largeFile,
            'filename' => 'large.txt',
        ],
    ];

    expect(MultipartBuilderFactory::shouldUseStreaming($smallParts))->toBeFalse()
        ->and(MultipartBuilderFactory::shouldUseStreaming($largeParts))->toBeTrue();
});

it('shouldUseStreaming recommends streaming when size unknown', function () {
    $parts = [
        [
            'name' => 'data',
            'contents' => ['invalid' => 'type'], // Invalid content
        ],
    ];

    expect(MultipartBuilderFactory::shouldUseStreaming($parts))->toBeTrue();
});

it('formatSize formats bytes correctly', function () {
    expect(MultipartBuilderFactory::formatSize(0))->toBe('0.0 B')
        ->and(MultipartBuilderFactory::formatSize(1))->toBe('1.0 B')
        ->and(MultipartBuilderFactory::formatSize(1024))->toBe('1.0 KB')
        ->and(MultipartBuilderFactory::formatSize(1024 * 1024))->toBe('1.0 MB')
        ->and(MultipartBuilderFactory::formatSize(1024 * 1024 * 1024))->toBe('1.0 GB');
});

it('formatSize formats with decimal', function () {
    expect(MultipartBuilderFactory::formatSize(1536))->toBe('1.5 KB')
        ->and(MultipartBuilderFactory::formatSize(2621440))->toBe('2.5 MB')
        ->and(MultipartBuilderFactory::formatSize(11274289152))->toBe('10.5 GB');
});

it('formatSize handles large sizes', function () {
    // Test TB
    $oneTerabyte = 1024 * 1024 * 1024 * 1024;
    $formatted = MultipartBuilderFactory::formatSize($oneTerabyte);

    expect($formatted)->toContain('TB');
});

it('getDefaultThreshold returns 10MB', function () {
    $threshold = MultipartBuilderFactory::getDefaultThreshold();

    expect($threshold)->toBe(10 * 1024 * 1024)
        ->and($threshold)->toBe(10485760);
});

it('getDefaultThresholdFormatted returns formatted string', function () {
    $formatted = MultipartBuilderFactory::getDefaultThresholdFormatted();

    expect($formatted)->toBe('10.0 MB');
});

it('handles multiple files total size calculation', function () {
    $parts = [
        [
            'name' => 'file1',
            'contents' => $this->smallFile,
            'filename' => 'file1.txt',
        ],
        [
            'name' => 'file2',
            'contents' => $this->smallFile,
            'filename' => 'file2.txt',
        ],
    ];

    // 2KB total < 10MB threshold
    $builder = MultipartBuilderFactory::create(null, $parts);

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('handles mixed content types', function () {
    $stream = $this->factory->createStream('Stream data');

    $parts = [
        ['name' => 'text', 'contents' => 'Text field'],
        [
            'name' => 'file',
            'contents' => $this->smallFile,
            'filename' => 'file.txt',
        ],
        [
            'name' => 'stream',
            'contents' => $stream,
            'filename' => 'data.txt',
        ],
    ];

    $builder = MultipartBuilderFactory::create(null, $parts);

    // Total should be small, so standard builder
    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('handles empty parts array', function () {
    $builder = MultipartBuilderFactory::create(null, []);

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('skips invalid parts in size calculation', function () {
    $parts = [
        ['name' => 'valid', 'contents' => 'Valid content'],
        ['name' => 'no_contents'], // Missing contents
        'invalid_structure', // Not an array
    ];

    $shouldStream = MultipartBuilderFactory::shouldUseStreaming($parts, 100);

    // Should still work, only counting valid parts
    expect($shouldStream)->toBeFalse();
});

it('handles file that cannot get size', function () {
    $parts = [
        [
            'name' => 'resource',
            'contents' => tmpfile(), // Resource without size
        ],
    ];

    // Should return streaming builder when size cannot be determined
    $builder = MultipartBuilderFactory::create(null, $parts);

    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('distinguishes string content from file path', function () {
    $parts = [
        // String content without filename - treated as text field
        ['name' => 'field', 'contents' => 'Just text'],
        // String with filename but not a real file - still treated as string
        ['name' => 'fake_file', 'contents' => '/not/a/file', 'filename' => 'fake.txt'],
    ];

    $shouldStream = MultipartBuilderFactory::shouldUseStreaming($parts, 100);

    expect($shouldStream)->toBeFalse(); // Small text content
});

it('create accepts HttpFactory parameter', function () {
    $factory = HttpFactory::getInstance();

    $builder = MultipartBuilderFactory::create($factory);

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('create accepts custom boundary', function () {
    $boundary = 'MyCustomBoundary';

    $builder = MultipartBuilderFactory::create(null, null, boundary: $boundary);

    expect($builder->getBoundary())->toBe($boundary);
});

it('threshold exactly at boundary uses streaming', function () {
    $threshold = 1024; // 1 KB
    $parts = [
        ['name' => 'text', 'contents' => str_repeat('a', 1024)], // Exactly 1 KB
    ];

    $builder = MultipartBuilderFactory::create(null, $parts, $threshold);

    // At threshold or above = streaming
    expect($builder)->toBeInstanceOf(StreamingMultipartBuilder::class);
});

it('just below threshold uses standard', function () {
    $threshold = 1024; // 1 KB
    $parts = [
        ['name' => 'text', 'contents' => str_repeat('a', 1023)], // Just below
    ];

    $builder = MultipartBuilderFactory::create(null, $parts, $threshold);

    expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
});

it('formatSize handles fractional KB', function () {
    $size = 1536; // 1.5 KB
    $formatted = MultipartBuilderFactory::formatSize($size);

    expect($formatted)->toBe('1.5 KB');
});

it('formatSize handles bytes less than 1KB', function () {
    expect(MultipartBuilderFactory::formatSize(512))->toBe('512.0 B')
        ->and(MultipartBuilderFactory::formatSize(100))->toBe('100.0 B');
});
