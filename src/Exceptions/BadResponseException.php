<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

/**
 * Exception thrown when a bad HTTP response is received.
 *
 * This is a general exception for non-2xx responses that don't fit
 * into more specific categories (like ClientException or ServerException).
 *
 * @example
 * ```php
 * try {
 *     $response = $transport->get('/api/endpoint')->send()->throw();
 * } catch (BadResponseException $e) {
 *     // Handle bad response (any non-2xx status)
 *     echo "Bad response: " . $e->getStatusCode();
 * }
 * ```
 */
class BadResponseException extends HttpException
{
    //
}
