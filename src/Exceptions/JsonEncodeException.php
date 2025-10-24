<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Throwable;

/**
 * Exception thrown when JSON encoding fails.
 *
 * This exception provides detailed information about what went wrong
 * during JSON encoding, including the error code and the data that failed.
 */
class JsonEncodeException extends SerializationException
{
    /**
     * Create a new JSON encode exception.
     *
     * @param  string  $message  The error message
     * @param  mixed  $value  The value that failed to encode
     * @param  int  $jsonErrorCode  The JSON error code
     * @param  string  $jsonErrorMessage  The JSON error message
     * @param  int  $depth  The nesting depth used
     * @param  Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $message,
        public readonly mixed $value,
        public readonly int $jsonErrorCode,
        public readonly string $jsonErrorMessage,
        public readonly int $depth = 512,
        ?Throwable $previous = null
    ) {
        $dataSize = is_string($value) ? strlen($value) : strlen(serialize($value));

        parent::__construct(
            message: $message,
            data: is_scalar($value) ? (string) $value : serialize($value),
            dataSize: $dataSize,
            format: 'JSON',
            previous: $previous
        );
    }

    /**
     * Create from a JsonException.
     *
     * @param  \JsonException  $exception  The JsonException
     * @param  mixed  $value  The value that failed to encode
     * @param  int  $depth  The nesting depth used
     */
    public static function fromJsonException(\JsonException $exception, mixed $value, int $depth = 512): static
    {
        return new static(
            message: sprintf('Failed to encode JSON: %s', $exception->getMessage()),
            value: $value,
            jsonErrorCode: $exception->getCode(),
            jsonErrorMessage: $exception->getMessage(),
            depth: $depth,
            previous: $exception
        );
    }
}
