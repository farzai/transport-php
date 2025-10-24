<?php

declare(strict_types=1);

namespace Farzai\Transport\Middleware;

use Farzai\Transport\Cookie\CookieJar;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware for automatic cookie handling.
 *
 * This middleware automatically:
 * - Adds cookies from the jar to outgoing requests
 * - Extracts Set-Cookie headers from responses and stores in jar
 * - Handles cookie expiration
 * - Respects secure, domain, and path restrictions
 *
 * Features:
 * - Automatic cookie persistence across requests
 * - RFC 6265 compliant cookie handling
 * - Thread-safe cookie operations
 */
final class CookieMiddleware implements MiddlewareInterface
{
    /**
     * Create a new cookie middleware.
     *
     * @param  CookieJar  $cookieJar  The cookie jar to use
     */
    public function __construct(
        private readonly CookieJar $cookieJar
    ) {
        //
    }

    /**
     * Handle the request with cookie management.
     *
     * @param  RequestInterface  $request  The request
     * @param  callable  $next  The next handler
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add cookies from jar to request
        $request = $this->addCookiesToRequest($request);

        // Send request and get response
        $response = $next($request);

        // Extract and store cookies from response
        $this->extractCookiesFromResponse($response, (string) $request->getUri());

        return $response;
    }

    /**
     * Add cookies from jar to the request.
     */
    private function addCookiesToRequest(RequestInterface $request): RequestInterface
    {
        $url = (string) $request->getUri();
        $scheme = $request->getUri()->getScheme();
        $isSecure = $scheme === 'https';

        // Get cookie header value for this URL
        $cookieHeader = $this->cookieJar->getCookieHeaderForUrl($url, $isSecure);

        if ($cookieHeader === null) {
            return $request;
        }

        // Check if request already has cookies
        $existingCookies = $request->getHeaderLine('Cookie');

        if ($existingCookies !== '') {
            // Merge with existing cookies
            $cookieHeader = $existingCookies.'; '.$cookieHeader;
        }

        return $request->withHeader('Cookie', $cookieHeader);
    }

    /**
     * Extract cookies from response and add to jar.
     */
    private function extractCookiesFromResponse(ResponseInterface $response, string $url): void
    {
        // Get all Set-Cookie headers
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        if (empty($setCookieHeaders)) {
            return;
        }

        // Add cookies to jar
        $this->cookieJar->addFromSetCookieHeaders($setCookieHeaders, $url);
    }

    /**
     * Get the cookie jar instance.
     */
    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }

    /**
     * Create a new middleware with a fresh cookie jar.
     */
    public static function create(?CookieJar $cookieJar = null): self
    {
        return new self($cookieJar ?? new CookieJar);
    }

    /**
     * Create middleware with session cookie persistence.
     */
    public static function withSessionPersistence(): self
    {
        return new self(CookieJar::withSessionPersistence());
    }
}
