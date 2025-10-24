<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

/**
 * Exception thrown for 5xx HTTP server errors.
 *
 * This exception is thrown when the server responds with a 5xx status code,
 * indicating that the server encountered an error processing the request.
 *
 * Common 5xx errors:
 * - 500 Internal Server Error
 * - 502 Bad Gateway
 * - 503 Service Unavailable
 * - 504 Gateway Timeout
 *
 * These errors typically indicate temporary server issues and might benefit
 * from retry logic.
 *
 * @example
 * ```php
 * try {
 *     $response = $transport->get('/api/endpoint')->send()->throw();
 * } catch (ServerException $e) {
 *     // Log server error for monitoring
 *     $logger->error('Server error', $e->getContext());
 *
 *     // Maybe retry or show user-friendly message
 *     if ($e->getStatusCode() === 503) {
 *         echo "Service temporarily unavailable. Please try again later.";
 *     }
 * }
 * ```
 */
class ServerException extends BadResponseException
{
    //
}
