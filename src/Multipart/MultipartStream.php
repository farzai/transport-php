<?php

declare(strict_types=1);

namespace Farzai\Transport\Multipart;

use Psr\Http\Message\StreamInterface;

/**
 * Custom PSR-7 stream implementation for streaming multipart data.
 *
 * Design Pattern: Iterator Pattern
 * - Implements lazy evaluation
 * - Yields data chunks on-demand
 * - Maintains internal state (position, current part, etc.)
 *
 * Memory Characteristics:
 * - O(1) memory usage (constant, regardless of data size)
 * - Only chunk size in memory at any time (~8KB default)
 * - Files never fully loaded into memory
 *
 * Limitations:
 * - Cannot seek (not seekable)
 * - Cannot rewind (one-time read)
 * - Cannot get size upfront (unknown size)
 *
 * @example
 * ```php
 * $stream = new MultipartStream($parts, $boundary, 8192);
 *
 * // Read in chunks
 * while (!$stream->eof()) {
 *     $chunk = $stream->read(8192);
 *     // Send chunk...
 * }
 * ```
 */
final class MultipartStream implements StreamInterface
{
    /**
     * @var array<Part>
     */
    private array $parts;

    private string $boundary;

    private int $chunkSize;

    /**
     * Current state of the stream.
     */
    private int $currentPartIndex = 0;

    /** @var resource|null */
    private $currentFileHandle = null;

    private string $buffer = '';

    private bool $eof = false;

    private int $position = 0;

    /**
     * Whether we've written the boundary for the current part.
     */
    private bool $currentPartBoundaryWritten = false;

    /**
     * Whether we've written the headers for the current part.
     */
    private bool $currentPartHeadersWritten = false;

    /**
     * Whether we've written the content for the current part.
     */
    private bool $currentPartContentWritten = false;

    /**
     * Create a new multipart stream.
     *
     * @param  array<Part>  $parts  The parts to stream
     * @param  string  $boundary  The boundary string
     * @param  int  $chunkSize  Chunk size for reading files
     */
    public function __construct(
        array $parts,
        string $boundary,
        int $chunkSize
    ) {
        $this->parts = $parts;
        $this->boundary = $boundary;
        $this->chunkSize = $chunkSize;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        try {
            $this->rewind();

            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->currentFileHandle !== null) {
            fclose($this->currentFileHandle);
            $this->currentFileHandle = null;
        }

        $this->eof = true;
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        $handle = $this->currentFileHandle;
        $this->currentFileHandle = null;
        $this->eof = true;

        return $handle;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): ?int
    {
        // Cannot determine size without reading all data
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function eof(): bool
    {
        return $this->eof;
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Stream is not seekable');
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        throw new \RuntimeException('Stream cannot be rewound');
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $string): int
    {
        throw new \RuntimeException('Stream is not writable');
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $length): string
    {
        if ($this->eof) {
            return '';
        }

        // Fill buffer until we have enough data or reach EOF
        while (strlen($this->buffer) < $length && ! $this->eof) {
            $this->fillBuffer();
        }

        // Extract requested amount from buffer
        $data = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        $this->position += strlen($data);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(): string
    {
        $contents = '';

        while (! $this->eof()) {
            $contents .= $this->read($this->chunkSize);
        }

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(?string $key = null): mixed
    {
        $metadata = [
            'boundary' => $this->boundary,
            'chunk_size' => $this->chunkSize,
            'parts_count' => count($this->parts),
            'current_part' => $this->currentPartIndex,
            'eof' => $this->eof,
        ];

        if ($key === null) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }

    /**
     * Fill the internal buffer with more data.
     *
     * This is where the actual streaming magic happens.
     */
    private function fillBuffer(): void
    {
        // Check if we've processed all parts
        if ($this->currentPartIndex >= count($this->parts)) {
            // Add closing boundary
            if (! $this->eof) {
                $this->buffer .= "--{$this->boundary}--\r\n";
                $this->eof = true;
            }

            return;
        }

        $part = $this->parts[$this->currentPartIndex];

        // Write part boundary
        if (! $this->currentPartBoundaryWritten) {
            $this->buffer .= "--{$this->boundary}\r\n";
            $this->currentPartBoundaryWritten = true;

            return;
        }

        // Write part headers
        if (! $this->currentPartHeadersWritten) {
            foreach ($part->getHeaders() as $name => $value) {
                $this->buffer .= "{$name}: {$value}\r\n";
            }
            $this->buffer .= "\r\n"; // Header/body separator
            $this->currentPartHeadersWritten = true;

            return;
        }

        // Write part content
        if (! $this->currentPartContentWritten) {
            $contents = $part->getContents();

            if ($contents instanceof StreamInterface) {
                // Stream file in chunks
                if ($this->currentFileHandle === null) {
                    // Get underlying resource or read from stream
                    $data = $contents->read($this->chunkSize);
                    if ($data !== '') {
                        $this->buffer .= $data;

                        return;
                    }
                }

                // If stream is EOF, mark content as written
                if ($contents->eof()) {
                    $this->currentPartContentWritten = true;
                    $this->buffer .= "\r\n";
                }
            } else {
                // String content - add all at once
                $this->buffer .= $contents;
                $this->currentPartContentWritten = true;
                $this->buffer .= "\r\n";
            }

            return;
        }

        // Move to next part
        $this->currentPartIndex++;
        $this->currentPartBoundaryWritten = false;
        $this->currentPartHeadersWritten = false;
        $this->currentPartContentWritten = false;

        if ($this->currentFileHandle !== null) {
            fclose($this->currentFileHandle);
            $this->currentFileHandle = null;
        }
    }

    /**
     * Destructor - ensure file handles are closed.
     */
    public function __destruct()
    {
        $this->close();
    }
}
