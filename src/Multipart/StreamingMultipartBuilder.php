<?php

declare(strict_types=1);

namespace Farzai\Transport\Multipart;

use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\StreamInterface;

/**
 * Streaming builder for creating memory-efficient multipart/form-data streams.
 *
 * Design Pattern: Iterator Pattern
 * - Yields data chunks on-demand (lazy evaluation)
 * - Reads files in small chunks (8KB default)
 * - Constant memory usage regardless of file size
 *
 * Use Cases:
 * - Large file uploads (>= 10 MB)
 * - Multiple file uploads
 * - Memory-constrained environments
 * - Concurrent uploads
 *
 * Differences from MultipartStreamBuilder:
 * - MultipartStreamBuilder: Loads entire body into memory (fast for small files)
 * - StreamingMultipartBuilder: Streams in chunks (memory-efficient for large files)
 *
 * @example
 * ```php
 * $builder = new StreamingMultipartBuilder($httpFactory);
 * $stream = $builder
 *     ->addFile('video', '/path/to/large-video.mp4')
 *     ->addField('title', 'My Video')
 *     ->build();
 *
 * // File read in 8KB chunks during transmission, not loaded upfront
 * $response = $transport->request()
 *     ->withBody($stream)
 *     ->withHeader('Content-Type', $builder->getContentType())
 *     ->post('/upload');
 * ```
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7578
 */
final class StreamingMultipartBuilder
{
    /**
     * @var array<Part>
     */
    private array $parts = [];

    private readonly string $boundary;

    private readonly HttpFactory $httpFactory;

    /**
     * Chunk size for reading files (8KB default).
     *
     * Smaller = Lower memory, more CPU
     * Larger = Higher memory, less CPU
     */
    private int $chunkSize = 8192;

    /**
     * Create a new streaming multipart builder.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     * @param  string|null  $boundary  Optional custom boundary
     * @param  int  $chunkSize  Chunk size in bytes (default 8192)
     */
    public function __construct(
        ?HttpFactory $httpFactory = null,
        ?string $boundary = null,
        int $chunkSize = 8192
    ) {
        $this->httpFactory = $httpFactory ?? HttpFactory::getInstance();
        $this->boundary = $boundary ?? $this->generateBoundary();
        $this->chunkSize = max(1024, $chunkSize); // Minimum 1KB
    }

    /**
     * Add a text field.
     *
     * @param  string  $name  Field name
     * @param  string  $value  Field value
     * @return $this
     */
    public function addField(string $name, string $value): self
    {
        $this->parts[] = Part::text($name, $value);

        return $this;
    }

    /**
     * Add a file from a path (will be streamed during upload).
     *
     * @param  string  $name  Field name
     * @param  string  $path  File path
     * @param  string|null  $filename  Optional custom filename
     * @param  string|null  $contentType  Optional content type
     * @return $this
     *
     * @throws \RuntimeException If file cannot be read
     */
    public function addFile(
        string $name,
        string $path,
        ?string $filename = null,
        ?string $contentType = null
    ): self {
        if (! is_readable($path)) {
            throw new \RuntimeException("File not readable: {$path}");
        }

        if (! is_file($path)) {
            throw new \RuntimeException("Not a file: {$path}");
        }

        $filename = $filename ?? basename($path);

        // Store path instead of loading file - will be streamed later
        $stream = $this->httpFactory->createStreamFromFile($path, 'r');
        $this->parts[] = Part::file($name, $stream, $filename, $contentType);

        return $this;
    }

    /**
     * Add a file from contents.
     *
     * Note: If contents is a string, it will be loaded into memory.
     * For large content, use addFile() with a file path instead.
     *
     * @param  string  $name  Field name
     * @param  StreamInterface|string  $contents  File contents
     * @param  string  $filename  Filename
     * @param  string|null  $contentType  Optional content type
     * @return $this
     */
    public function addFileContents(
        string $name,
        StreamInterface|string $contents,
        string $filename,
        ?string $contentType = null
    ): self {
        $this->parts[] = Part::file($name, $contents, $filename, $contentType);

        return $this;
    }

    /**
     * Add a custom part.
     *
     * @param  Part  $part  The part to add
     * @return $this
     */
    public function addPart(Part $part): self
    {
        $this->parts[] = $part;

        return $this;
    }

    /**
     * Add multiple parts from an array.
     *
     * @param  array<mixed>  $parts
     * @return $this
     */
    public function addMultiple(array $parts): self
    {
        foreach ($parts as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['contents'])) {
                $this->addFromArray($value);
            } elseif (is_string($key)) {
                $this->addField($key, (string) $value);
            }
        }

        return $this;
    }

    /**
     * Get the boundary string.
     */
    public function getBoundary(): string
    {
        return $this->boundary;
    }

    /**
     * Get the Content-Type header value.
     */
    public function getContentType(): string
    {
        return 'multipart/form-data; boundary='.$this->boundary;
    }

    /**
     * Set chunk size for file reading.
     *
     * @param  int  $bytes  Chunk size in bytes (minimum 1024)
     * @return $this
     */
    public function setChunkSize(int $bytes): self
    {
        $this->chunkSize = max(1024, $bytes);

        return $this;
    }

    /**
     * Get the chunk size.
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Build the streaming multipart stream.
     *
     * Returns a custom StreamInterface that yields data on-demand.
     * The stream uses the Iterator pattern internally to read file chunks lazily.
     *
     * @return StreamInterface The streaming multipart stream
     */
    public function build(): StreamInterface
    {
        return new MultipartStream(
            $this->parts,
            $this->boundary,
            $this->chunkSize
        );
    }

    /**
     * Get all parts.
     *
     * @return array<Part>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Count the number of parts.
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * Calculate total size of the multipart body.
     *
     * Note: This may be slow for large files as it needs to determine each part's size.
     * Returns null if size cannot be determined.
     */
    public function getSize(): ?int
    {
        $size = 0;

        foreach ($this->parts as $part) {
            // Boundary + CRLF
            $size += strlen("--{$this->boundary}\r\n");

            // Headers
            foreach ($part->getHeaders() as $name => $value) {
                $size += strlen("{$name}: {$value}\r\n");
            }

            // Header/body separator
            $size += 2; // \r\n

            // Content size
            $partSize = $part->getSize();
            if ($partSize === null) {
                return null; // Cannot determine size
            }
            $size += $partSize;

            // CRLF after content
            $size += 2;
        }

        // Closing boundary
        $size += strlen("--{$this->boundary}--\r\n");

        return $size;
    }

    /**
     * Add a part from array configuration.
     *
     * @param  array<string, mixed>  $config
     */
    private function addFromArray(array $config): void
    {
        $name = (string) $config['name'];
        $contents = $config['contents'];
        $filename = $config['filename'] ?? null;
        $contentType = $config['content-type'] ?? $config['contentType'] ?? null;

        // File path
        if (is_string($contents) && isset($config['filename']) && is_file($contents)) {
            $this->addFile($name, $contents, $filename, $contentType);

            return;
        }

        // File with contents
        if ($filename !== null) {
            if (is_string($contents)) {
                $contents = $this->httpFactory->createStream($contents);
            }

            $this->addFileContents($name, $contents, $filename, $contentType);

            return;
        }

        // Regular field
        $this->addField($name, (string) $contents);
    }

    /**
     * Generate a unique boundary string.
     */
    private function generateBoundary(): string
    {
        return '----TransportPHPStreaming'.bin2hex(random_bytes(16));
    }

    /**
     * Create a new builder instance.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     * @param  string|null  $boundary  Optional custom boundary
     */
    public static function create(?HttpFactory $httpFactory = null, ?string $boundary = null): self
    {
        return new self($httpFactory, $boundary);
    }
}
