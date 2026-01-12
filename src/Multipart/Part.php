<?php

declare(strict_types=1);

namespace Farzai\Transport\Multipart;

use Psr\Http\Message\StreamInterface;

/**
 * Represents a single part in a multipart/form-data request.
 *
 * This class encapsulates a form field or file upload part with its
 * associated metadata (name, filename, headers, content).
 *
 * Follows RFC 7578 (multipart/form-data) specification.
 */
final class Part
{
    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Create a new multipart part.
     *
     * @param  string  $name  The field name
     * @param  StreamInterface|string  $contents  The content stream or string
     * @param  string|null  $filename  Optional filename (for file uploads)
     * @param  array<string, string>  $headers  Optional custom headers
     */
    public function __construct(
        private readonly string $name,
        private readonly StreamInterface|string $contents,
        private readonly ?string $filename = null,
        array $headers = []
    ) {
        $this->headers = $headers;
        $this->initializeDefaultHeaders();
    }

    /**
     * Get the field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the contents.
     */
    public function getContents(): StreamInterface|string
    {
        return $this->contents;
    }

    /**
     * Get the filename (if this is a file upload).
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get all headers for this part.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Check if this part represents a file upload.
     */
    public function isFile(): bool
    {
        return $this->filename !== null;
    }

    /**
     * Get the Content-Disposition header value.
     */
    public function getContentDisposition(): string
    {
        $disposition = 'form-data; name="'.$this->escapeQuotes($this->name).'"';

        if ($this->filename !== null) {
            $disposition .= '; filename="'.$this->escapeQuotes($this->filename).'"';
        }

        return $disposition;
    }

    /**
     * Get the size of the content.
     */
    public function getSize(): ?int
    {
        if ($this->contents instanceof StreamInterface) {
            return $this->contents->getSize();
        }

        return strlen($this->contents);
    }

    /**
     * Initialize default headers based on content type.
     */
    private function initializeDefaultHeaders(): void
    {
        // Set Content-Disposition
        if (! isset($this->headers['Content-Disposition'])) {
            $this->headers['Content-Disposition'] = $this->getContentDisposition();
        }

        // Set Content-Type for file uploads if not already set
        if ($this->isFile() && ! isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = $this->guessContentType();
        }
    }

    /**
     * Guess the content type based on filename.
     */
    private function guessContentType(): string
    {
        if ($this->filename === null) {
            return 'application/octet-stream';
        }

        $extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * Escape quotes in field/filename values.
     */
    private function escapeQuotes(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    /**
     * Create a text field part.
     *
     * @param  string  $name  Field name
     * @param  string  $value  Field value
     */
    public static function text(string $name, string $value): self
    {
        return new self($name, $value);
    }

    /**
     * Create a file upload part.
     *
     * @param  string  $name  Field name
     * @param  StreamInterface|string  $contents  File contents
     * @param  string  $filename  Filename
     * @param  string|null  $contentType  Optional content type
     */
    public static function file(
        string $name,
        StreamInterface|string $contents,
        string $filename,
        ?string $contentType = null
    ): self {
        $headers = [];
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        return new self($name, $contents, $filename, $headers);
    }
}
