<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

/**
 * Base exception for all serialization-related errors.
 *
 * This exception is thrown when encoding or decoding data fails,
 * regardless of the serialization format (JSON, XML, etc.).
 */
class SerializationException extends TransportException
{
    /**
     * Create a new serialization exception.
     *
     * @param  string  $message  The error message
     * @param  string|null  $data  The data that failed to serialize (truncated if too large)
     * @param  int  $dataSize  The size of the data in bytes
     * @param  string  $format  The serialization format (e.g., "JSON", "XML")
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $message,
        public readonly ?string $data = null,
        public readonly int $dataSize = 0,
        public readonly string $format = 'unknown',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get a truncated version of the data for safe logging.
     *
     * @param  int  $maxLength  Maximum length of the truncated string
     * @return string|null The truncated data or null if no data
     */
    public function getTruncatedData(int $maxLength = 200): ?string
    {
        if ($this->data === null) {
            return null;
        }

        if (strlen($this->data) <= $maxLength) {
            return $this->data;
        }

        return substr($this->data, 0, $maxLength).' ... (truncated)';
    }
}
