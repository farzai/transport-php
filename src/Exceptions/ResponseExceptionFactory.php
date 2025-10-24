<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Farzai\Transport\Contracts\ResponseInterface;
use Throwable;

/**
 * Factory for creating appropriate HTTP exception instances.
 *
 * This factory analyzes HTTP responses and creates the appropriate
 * exception type based on the status code and response content.
 *
 * Design Pattern: Factory Pattern
 * - Encapsulates exception creation logic
 * - Determines appropriate exception type based on HTTP status
 * - Extracts meaningful error messages from response bodies
 *
 * @example
 * ```php
 * if (!$response->isSuccessful()) {
 *     throw ResponseExceptionFactory::create($response);
 * }
 * ```
 */
class ResponseExceptionFactory
{
    /**
     * Create an appropriate exception instance based on the response.
     *
     * Exception types:
     * - ClientException: 4xx status codes (client errors)
     * - ServerException: 5xx status codes (server errors)
     * - BadResponseException: Other non-2xx status codes
     *
     * @param  ResponseInterface  $response  The HTTP response
     * @param  Throwable|null  $previous  Previous exception in the chain
     * @return HttpException The created exception
     *
     * @example
     * ```php
     * $exception = ResponseExceptionFactory::create($response);
     * // Returns ClientException for 404, ServerException for 500, etc.
     * ```
     */
    public static function create(ResponseInterface $response, ?Throwable $previous = null): HttpException
    {
        $statusCode = $response->statusCode();
        $message = static::getErrorMessage($response);
        $request = $response->getPsrRequest();

        // 4xx Client Errors
        if ($statusCode >= 400 && $statusCode < 500) {
            return new ClientException(
                $message,
                $request,
                $response,
                $previous,
                $statusCode
            );
        }

        // 5xx Server Errors
        if ($statusCode >= 500 && $statusCode < 600) {
            return new ServerException(
                $message,
                $request,
                $response,
                $previous,
                $statusCode
            );
        }

        // Other non-2xx responses
        return new BadResponseException(
            $message,
            $request,
            $response,
            $previous,
            $statusCode
        );
    }

    /**
     * Extract a meaningful error message from the response.
     *
     * This method attempts to extract error messages from common
     * response formats (JSON APIs, HTML, plain text) in order of preference:
     *
     * 1. JSON error fields (message, error, error_description, etc.)
     * 2. Raw response body
     * 3. HTTP status code description
     *
     * @param  ResponseInterface  $response  The HTTP response
     * @return string The extracted error message
     *
     * @example
     * ```php
     * // JSON API: {"error": "User not found"}
     * $message = ResponseExceptionFactory::getErrorMessage($response);
     * // Returns: "User not found"
     *
     * // Plain text response
     * $message = ResponseExceptionFactory::getErrorMessage($response);
     * // Returns: "404 Not Found"
     * ```
     */
    public static function getErrorMessage(ResponseInterface $response): string
    {
        $statusCode = $response->statusCode();

        // Try to extract error from JSON response
        $jsonError = static::extractJsonError($response);
        if ($jsonError !== null) {
            return $jsonError;
        }

        // Fall back to response body (truncated if too long)
        $body = $response->body();
        if (! empty($body) && strlen($body) < 500) {
            return $body;
        }

        // Fall back to HTTP status code description
        return sprintf(
            'HTTP %d %s',
            $statusCode,
            $response->getReasonPhrase()
        );
    }

    /**
     * Try to extract error message from JSON response.
     *
     * Checks common JSON error field names:
     * - message
     * - error
     * - error_message
     * - error_msg
     * - error_description
     * - errors (if array, joins them)
     *
     * @param  ResponseInterface  $response  The HTTP response
     * @return string|null The error message or null if not JSON
     */
    private static function extractJsonError(ResponseInterface $response): ?string
    {
        try {
            $json = $response->jsonOrNull();

            if (! is_array($json)) {
                return null;
            }

            // Common error field names in order of preference
            $errorFields = [
                'message',
                'error_message',
                'error_msg',
                'error_description',
                'error',
                'errors',
            ];

            foreach ($errorFields as $field) {
                if (isset($json[$field])) {
                    $value = $json[$field];

                    // Handle array of errors
                    if (is_array($value)) {
                        return implode('; ', array_filter($value));
                    }

                    // Handle string error
                    if (is_string($value) && ! empty($value)) {
                        return $value;
                    }
                }
            }

            return null;
        } catch (\Throwable) {
            // Not a JSON response or invalid JSON
            return null;
        }
    }
}

