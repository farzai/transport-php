<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Throwable;

/**
 * Exception thrown when JSON decoding/parsing fails.
 *
 * This exception provides detailed information about what went wrong
 * during JSON parsing, including the error code, the JSON string that
 * failed to parse, and the nesting depth used.
 */
class JsonParseException extends SerializationException
{
    /**
     * Create a new JSON parse exception.
     *
     * @param  string  $message  The error message
     * @param  string  $jsonString  The JSON string that failed to parse
     * @param  int  $jsonErrorCode  The JSON error code
     * @param  string  $jsonErrorMessage  The JSON error message
     * @param  int  $depth  The nesting depth used
     * @param  Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $message,
        public readonly string $jsonString,
        public readonly int $jsonErrorCode,
        public readonly string $jsonErrorMessage,
        public readonly int $depth = 512,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            data: $jsonString,
            dataSize: strlen($jsonString),
            format: 'JSON',
            previous: $previous
        );
    }

    /**
     * Create from a JsonException.
     *
     * @param  \JsonException  $exception  The JsonException
     * @param  string  $jsonString  The JSON string that failed to parse
     * @param  int  $depth  The nesting depth used
     */
    public static function fromJsonException(\JsonException $exception, string $jsonString, int $depth = 512): self
    {
        return new self(
            message: sprintf('Failed to parse JSON: %s', $exception->getMessage()),
            jsonString: $jsonString,
            jsonErrorCode: $exception->getCode(),
            jsonErrorMessage: $exception->getMessage(),
            depth: $depth,
            previous: $exception
        );
    }
}
