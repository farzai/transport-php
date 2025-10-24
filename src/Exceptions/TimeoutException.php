<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Psr\Http\Message\RequestInterface;
use Throwable;

class TimeoutException extends RequestException
{
    public function __construct(
        string $message,
        RequestInterface $request,
        public readonly int $timeoutSeconds,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $request, $previous);
    }
}
