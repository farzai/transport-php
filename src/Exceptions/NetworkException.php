<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Psr\Http\Message\RequestInterface;
use Throwable;

class NetworkException extends RequestException
{
    public function __construct(
        string $message,
        RequestInterface $request,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $request, $previous);
    }
}
