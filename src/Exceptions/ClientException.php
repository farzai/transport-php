<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

/**
 * Exception thrown for 4xx HTTP client errors.
 *
 * This exception is thrown when the server responds with a 4xx status code,
 * indicating that the client made an error in the request (bad request,
 * unauthorized, not found, etc.).
 *
 * Common 4xx errors:
 * - 400 Bad Request
 * - 401 Unauthorized
 * - 403 Forbidden
 * - 404 Not Found
 * - 422 Unprocessable Entity
 * - 429 Too Many Requests
 *
 * @example
 * ```php
 * try {
 *     $response = $transport->get('/api/users/999')->send()->throw();
 * } catch (ClientException $e) {
 *     if ($e->getStatusCode() === 404) {
 *         // Handle not found
 *     } elseif ($e->getStatusCode() === 401) {
 *         // Handle unauthorized
 *     }
 * }
 * ```
 */
class ClientException extends BadResponseException
{
    //
}
