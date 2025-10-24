<?php

declare(strict_types=1);

namespace Farzai\Transport\Multipart;

use Farzai\Transport\Factory\HttpFactory;
use Psr\Http\Message\StreamInterface;

/**
 * Builder for creating multipart/form-data streams.
 *
 * This class constructs RFC 7578 compliant multipart/form-data streams
 * with proper boundaries, headers, and content encoding.
 *
 * Features:
 * - Automatic boundary generation
 * - Mixed text and file fields
 * - PSR-7 StreamInterface compliance
 * - Memory-efficient streaming
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7578
 */
final class MultipartStreamBuilder
{
    /**
     * @var array<Part>
     */
    private array $parts = [];

    private readonly string $boundary;

    private readonly HttpFactory $httpFactory;

    /**
     * Create a new multipart stream builder.
     *
     * @param  string|null  $boundary  Optional custom boundary
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     */
    public function __construct(
        ?string $boundary = null,
        ?HttpFactory $httpFactory = null
    ) {
        $this->boundary = $boundary ?? $this->generateBoundary();
        $this->httpFactory = $httpFactory ?? HttpFactory::getInstance();
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
     * Add a file from a path.
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

        $filename = $filename ?? basename($path);
        $stream = $this->httpFactory->createStreamFromFile($path, 'r');

        $this->parts[] = Part::file($name, $stream, $filename, $contentType);

        return $this;
    }

    /**
     * Add a file from contents (string or stream).
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
     * Supports both formats:
     * - ['name' => 'value', ...] for simple fields
     * - [['name' => '...', 'contents' => '...', 'filename' => '...'], ...]
     *
     * @param  array<mixed>  $parts
     * @return $this
     */
    public function addMultiple(array $parts): self
    {
        foreach ($parts as $key => $value) {
            // Array format with keys
            if (is_array($value) && isset($value['name'], $value['contents'])) {
                $this->addFromArray($value);
            } elseif (is_string($key)) {
                // Simple key-value format
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
     * Build the multipart stream.
     *
     * @return StreamInterface The complete multipart stream
     */
    public function build(): StreamInterface
    {
        $content = '';

        foreach ($this->parts as $part) {
            $content .= $this->buildPartContent($part);
        }

        // Add closing boundary
        $content .= "--{$this->boundary}--\r\n";

        return $this->httpFactory->createStream($content);
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
     * Build content for a single part.
     */
    private function buildPartContent(Part $part): string
    {
        $content = "--{$this->boundary}\r\n";

        // Add headers
        foreach ($part->getHeaders() as $name => $value) {
            $content .= "{$name}: {$value}\r\n";
        }

        $content .= "\r\n";

        // Add content
        $partContent = $part->getContents();
        if ($partContent instanceof StreamInterface) {
            $content .= $partContent->getContents();
        } else {
            $content .= $partContent;
        }

        $content .= "\r\n";

        return $content;
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

        // Convert string path to stream if it's a file path
        if (is_string($contents) && isset($config['filename']) && is_file($contents)) {
            $this->addFile($name, $contents, $filename, $contentType);

            return;
        }

        // File with contents
        if ($filename !== null) {
            // Convert string to stream if needed
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
        return '----TransportPHP'.bin2hex(random_bytes(16));
    }

    /**
     * Create a new builder instance.
     *
     * @param  string|null  $boundary  Optional custom boundary
     */
    public static function create(?string $boundary = null): self
    {
        return new self($boundary);
    }
}
