<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Throwable;

class JsonParseException extends TransportException
{
    public function __construct(
        string $message,
        public readonly string $jsonString,
        public readonly int $jsonErrorCode,
        public readonly string $jsonErrorMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
