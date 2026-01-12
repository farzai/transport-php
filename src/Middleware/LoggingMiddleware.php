<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * Headers that should be redacted from logs for security.
     *
     * @var array<string>
     */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'proxy-authorization',
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = (string) $request->getUri();

        $this->logger->info(sprintf('[REQUEST] %s %s', $method, $uri), [
            'method' => $method,
            'uri' => $uri,
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
        ]);

        try {
            $response = $next($request);

            $this->logger->info(sprintf('[RESPONSE] %d %s', $response->getStatusCode(), $uri), [
                'status' => $response->getStatusCode(),
                'method' => $method,
                'uri' => $uri,
            ]);

            return $response;
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('[ERROR] %s %s: %s', $method, $uri, $exception->getMessage()), [
                'method' => $method,
                'uri' => $uri,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Sanitize headers by redacting sensitive values.
     *
     * @param  array<string, array<string>>  $headers  The headers to sanitize
     * @return array<string, array<string>>  The sanitized headers
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), self::SENSITIVE_HEADERS, true)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }

        return $sanitized;
    }
}
