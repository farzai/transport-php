<?php

namespace Farzai\Transport;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ResponseFactory
{
    /**
     * Create a new response instance.
     */
    public static function create(
        int $statusCode,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        ?string $reason = null
    ): PsrResponseInterface {
        return new Response($statusCode, $headers, $body, $version, $reason);
    }
}
