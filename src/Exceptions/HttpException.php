<?php

declare(strict_types=1);

namespace Farzai\Transport\Exceptions;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Base exception for all HTTP-related errors.
 *
 * This exception provides rich context about HTTP failures including
 * the request, response (if available), and detailed error information.
 *
 * Design Pattern: Exception Hierarchy
 * - Provides consistent error handling across the library
 * - Includes request/response context for debugging
 * - Implements PSR-18 RequestExceptionInterface
 *
 * @example
 * ```php
 * try {
 *     $response = $transport->get('/api/endpoint')->send();
 * } catch (HttpException $e) {
 *     echo "HTTP Error: " . $e->getMessage();
 *     echo "Request: " . $e->getRequest()->getUri();
 *     if ($e->hasResponse()) {
 *         echo "Status: " . $e->getResponse()->getStatusCode();
 *     }
 * }
 * ```
 */
class HttpException extends RuntimeException implements RequestExceptionInterface
{
    /**
     * Create a new HTTP exception.
     *
     * @param  string  $message  Error message
     * @param  RequestInterface  $request  The request that caused the exception
     * @param  ResponseInterface|null  $response  The response (if available)
     * @param  Throwable|null  $previous  Previous exception
     * @param  int  $code  Error code
     */
    public function __construct(
        string $message,
        protected readonly RequestInterface $request,
        protected readonly ?ResponseInterface $response = null,
        ?Throwable $previous = null,
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the request that caused the exception.
     *
     * @return RequestInterface The HTTP request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the response if available.
     *
     * @return ResponseInterface|null The HTTP response or null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Check if a response is available.
     *
     * @return bool True if response is available
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /**
     * Get the HTTP status code if response is available.
     *
     * @return int|null The status code or null
     */
    public function getStatusCode(): ?int
    {
        return $this->response?->getStatusCode();
    }

    /**
     * Get a detailed error context for logging.
     *
     * @return array<string, mixed> Error context
     */
    public function getContext(): array
    {
        $context = [
            'message' => $this->getMessage(),
            'request' => [
                'method' => $this->request->getMethod(),
                'uri' => (string) $this->request->getUri(),
                'headers' => $this->request->getHeaders(),
            ],
        ];

        if ($this->hasResponse()) {
            $context['response'] = [
                'status_code' => $this->response->getStatusCode(),
                'reason_phrase' => $this->response->getReasonPhrase(),
                'headers' => $this->response->getHeaders(),
            ];
        }

        return $context;
    }
}
