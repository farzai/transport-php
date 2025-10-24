<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Psr\Http\Message\RequestInterface;
use Throwable;

class RequestException extends TransportException
{
    public function __construct(
        string $message,
        public readonly RequestInterface $request,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
