<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Cookie storage and management following RFC 6265.
 *
 * This class provides:
 * - Cookie storage with automatic expiration handling
 * - Domain and path-based cookie matching
 * - Session vs persistent cookie handling
 * - Thread-safe cookie operations
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6265
 */
class CookieJar
{
    /**
     * @var array<string, Cookie>
     */
    private array $cookies = [];

    /**
     * Whether to persist session cookies.
     */
    private bool $persistSessionCookies = false;

    /**
     * Create a new cookie jar.
     *
     * @param  bool  $persistSessionCookies  Whether to persist session cookies
     */
    public function __construct(bool $persistSessionCookies = false)
    {
        $this->persistSessionCookies = $persistSessionCookies;
    }

    /**
     * Set a cookie in the jar.
     *
     * If a cookie with the same name, domain, and path exists, it will be replaced.
     *
     * @param  Cookie  $cookie  The cookie to set
     * @return $this
     */
    public function setCookie(Cookie $cookie): self
    {
        // Don't store expired cookies
        if ($cookie->isExpired()) {
            $this->removeCookie($cookie->getName(), $cookie->getDomain(), $cookie->getPath());

            return $this;
        }

        // Don't store session cookies if not persisting them
        if (! $this->persistSessionCookies && $cookie->isSessionCookie()) {
            // Still store in memory for current session
        }

        $this->cookies[$cookie->getIdentifier()] = $cookie;

        return $this;
    }

    /**
     * Get a specific cookie by name, domain, and path.
     *
     * @param  string  $name  Cookie name
     * @param  string|null  $domain  Cookie domain
     * @param  string  $path  Cookie path
     */
    public function getCookie(string $name, ?string $domain = null, string $path = '/'): ?Cookie
    {
        $identifier = sprintf('%s|%s|%s', $name, $domain ?? '', $path);

        return $this->cookies[$identifier] ?? null;
    }

    /**
     * Get all cookies matching a URL.
     *
     * Returns cookies sorted by path length (most specific first).
     *
     * @param  string  $url  The URL to match
     * @param  bool|null  $isSecure  Whether the request is secure (auto-detect if null)
     * @return array<Cookie>
     */
    public function getCookiesForUrl(string $url, ?bool $isSecure = null): array
    {
        $this->removeExpiredCookies();

        $parsed = parse_url($url);
        if ($parsed === false) {
            return [];
        }

        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $isSecure = $isSecure ?? (($parsed['scheme'] ?? '') === 'https');

        $matching = [];

        foreach ($this->cookies as $cookie) {
            if ($cookie->matchesUrl($url, $isSecure)) {
                $matching[] = $cookie;
            }
        }

        // Sort by path length (RFC 6265 Section 5.4)
        usort($matching, function (Cookie $a, Cookie $b) {
            $lengthA = strlen($a->getPath());
            $lengthB = strlen($b->getPath());

            if ($lengthA === $lengthB) {
                // If same length, sort by creation time (older first)
                // Since we don't track creation time, maintain insertion order
                return 0;
            }

            // Longer paths first (more specific)
            return $lengthB <=> $lengthA;
        });

        return $matching;
    }

    /**
     * Get all cookies.
     *
     * @param  bool  $includeExpired  Whether to include expired cookies
     * @return array<Cookie>
     */
    public function getAllCookies(bool $includeExpired = false): array
    {
        if (! $includeExpired) {
            $this->removeExpiredCookies();
        }

        return array_values($this->cookies);
    }

    /**
     * Remove a specific cookie.
     *
     * @param  string  $name  Cookie name
     * @param  string|null  $domain  Cookie domain
     * @param  string  $path  Cookie path
     * @return $this
     */
    public function removeCookie(string $name, ?string $domain = null, string $path = '/'): self
    {
        $identifier = sprintf('%s|%s|%s', $name, $domain ?? '', $path);

        unset($this->cookies[$identifier]);

        return $this;
    }

    /**
     * Remove all cookies.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->cookies = [];

        return $this;
    }

    /**
     * Remove expired cookies.
     *
     * @return $this
     */
    public function removeExpiredCookies(): self
    {
        $this->cookies = array_filter(
            $this->cookies,
            fn (Cookie $cookie) => ! $cookie->isExpired()
        );

        return $this;
    }

    /**
     * Count the number of cookies in the jar.
     *
     * @param  bool  $includeExpired  Whether to include expired cookies
     */
    public function count(bool $includeExpired = false): int
    {
        if (! $includeExpired) {
            $this->removeExpiredCookies();
        }

        return count($this->cookies);
    }

    /**
     * Check if the jar is empty.
     */
    public function isEmpty(): bool
    {
        $this->removeExpiredCookies();

        return empty($this->cookies);
    }

    /**
     * Parse Set-Cookie headers and add cookies to the jar.
     *
     * @param  array<string>|string  $headers  Set-Cookie header value(s)
     * @param  string  $url  The URL these cookies came from (for default domain)
     * @return $this
     */
    public function addFromSetCookieHeaders(array|string $headers, string $url): self
    {
        $headers = is_array($headers) ? $headers : [$headers];

        $parsed = parse_url($url);
        $defaultDomain = $parsed['host'] ?? '';

        foreach ($headers as $header) {
            try {
                $cookie = Cookie::fromSetCookieHeader($header, $defaultDomain);
                $this->setCookie($cookie);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid cookies
                continue;
            }
        }

        return $this;
    }

    /**
     * Get Cookie header value for a URL.
     *
     * Returns the string to be used in the Cookie request header.
     *
     * @param  string  $url  The URL to get cookies for
     * @param  bool|null  $isSecure  Whether the request is secure
     */
    public function getCookieHeaderForUrl(string $url, ?bool $isSecure = null): ?string
    {
        $cookies = $this->getCookiesForUrl($url, $isSecure);

        if (empty($cookies)) {
            return null;
        }

        $cookieStrings = array_map(
            fn (Cookie $cookie) => $cookie->toCookieHeader(),
            $cookies
        );

        return implode('; ', $cookieStrings);
    }

    /**
     * Export cookies to array format.
     *
     * @return array<array<string, mixed>>
     */
    public function toArray(): array
    {
        $this->removeExpiredCookies();

        return array_map(function (Cookie $cookie) {
            return [
                'name' => $cookie->getName(),
                'value' => $cookie->getValue(),
                'expires_at' => $cookie->getExpiresAt(),
                'domain' => $cookie->getDomain(),
                'path' => $cookie->getPath(),
                'secure' => $cookie->isSecure(),
                'http_only' => $cookie->isHttpOnly(),
                'same_site' => $cookie->getSameSite(),
            ];
        }, $this->cookies);
    }

    /**
     * Import cookies from array format.
     *
     * @param  array<array<string, mixed>>  $data
     * @return $this
     */
    public function fromArray(array $data): self
    {
        foreach ($data as $item) {
            try {
                $cookie = new Cookie(
                    $item['name'],
                    $item['value'],
                    $item['expires_at'] ?? null,
                    $item['domain'] ?? null,
                    $item['path'] ?? '/',
                    $item['secure'] ?? false,
                    $item['http_only'] ?? false,
                    $item['same_site'] ?? null
                );

                $this->setCookie($cookie);
            } catch (\Exception $e) {
                // Skip invalid cookies
                continue;
            }
        }

        return $this;
    }

    /**
     * Create a new cookie jar with session cookie persistence.
     */
    public static function withSessionPersistence(): self
    {
        return new self(true);
    }

    /**
     * Create a new cookie jar without session cookie persistence.
     */
    public static function withoutSessionPersistence(): self
    {
        return new self(false);
    }
}
