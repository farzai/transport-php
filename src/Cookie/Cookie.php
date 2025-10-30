<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Represents an HTTP cookie following RFC 6265.
 *
 * This class encapsulates all cookie attributes and provides methods
 * for cookie validation, expiration checking, and domain/path matching.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6265
 */
final class Cookie
{
    private readonly string $name;

    private readonly string $value;

    private readonly ?int $expiresAt;

    private readonly ?string $domain;

    private readonly string $path;

    private readonly bool $secure;

    private readonly bool $httpOnly;

    private readonly ?string $sameSite;

    /**
     * Create a new cookie.
     *
     * @param  string  $name  Cookie name
     * @param  string  $value  Cookie value
     * @param  int|null  $expiresAt  Unix timestamp when cookie expires (null = session cookie)
     * @param  string|null  $domain  Cookie domain
     * @param  string  $path  Cookie path
     * @param  bool  $secure  Secure flag
     * @param  bool  $httpOnly  HttpOnly flag
     * @param  string|null  $sameSite  SameSite attribute (Strict, Lax, None, or null)
     */
    public function __construct(
        string $name,
        string $value,
        ?int $expiresAt = null,
        ?string $domain = null,
        string $path = '/',
        bool $secure = false,
        bool $httpOnly = false,
        ?string $sameSite = null
    ) {
        $this->validateName($name);
        $this->validateSameSite($sameSite);

        $this->name = $name;
        $this->value = $value;
        $this->expiresAt = $expiresAt;
        $this->domain = $domain ? strtolower($domain) : null;
        $this->path = $path;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }

    /**
     * Get the cookie name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the cookie value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the expiration timestamp.
     */
    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    /**
     * Get the domain.
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Get the path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Check if secure flag is set.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Check if HttpOnly flag is set.
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Get the SameSite attribute.
     */
    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    /**
     * Check if the cookie has expired.
     */
    public function isExpired(?int $currentTime = null): bool
    {
        if ($this->expiresAt === null) {
            return false; // Session cookie never expires until browser closes
        }

        $currentTime = $currentTime ?? time();

        return $currentTime >= $this->expiresAt;
    }

    /**
     * Check if this is a session cookie.
     */
    public function isSessionCookie(): bool
    {
        return $this->expiresAt === null;
    }

    /**
     * Check if the cookie matches a given domain.
     *
     * Implements domain matching as per RFC 6265 Section 5.1.3.
     *
     * @param  string  $requestDomain  The domain to check against
     */
    public function matchesDomain(string $requestDomain): bool
    {
        $requestDomain = strtolower($requestDomain);

        // If cookie has no domain, it only matches the exact origin domain
        if ($this->domain === null) {
            return true; // Let the jar handle origin domain matching
        }

        $cookieDomain = $this->domain;

        // Exact match
        if ($cookieDomain === $requestDomain) {
            return true;
        }

        // Domain cookie (starts with dot or not)
        if (! str_starts_with($cookieDomain, '.')) {
            $cookieDomain = '.'.$cookieDomain;
        }

        // Check if request domain ends with cookie domain
        if (str_ends_with('.'.$requestDomain, $cookieDomain)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the cookie matches a given path.
     *
     * Implements path matching as per RFC 6265 Section 5.1.4.
     *
     * @param  string  $requestPath  The path to check against
     */
    public function matchesPath(string $requestPath): bool
    {
        $cookiePath = $this->path;

        // Exact match
        if ($cookiePath === $requestPath) {
            return true;
        }

        // Cookie path must be a prefix of request path
        if (! str_starts_with($requestPath, $cookiePath)) {
            return false;
        }

        // If cookie path ends with '/', it matches
        if (str_ends_with($cookiePath, '/')) {
            return true;
        }

        // Next character in request path must be '/'
        return $requestPath[strlen($cookiePath)] === '/';
    }

    /**
     * Check if the cookie matches a given URL.
     *
     * @param  string  $url  The URL to check against
     * @param  bool  $isSecure  Whether the request is secure (HTTPS)
     */
    public function matchesUrl(string $url, ?bool $isSecure = null): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $isSecure = $isSecure ?? (($parsed['scheme'] ?? '') === 'https');

        // Check secure flag
        if ($this->secure && ! $isSecure) {
            return false;
        }

        // Check domain and path
        return $this->matchesDomain($domain) && $this->matchesPath($path);
    }

    /**
     * Convert cookie to Set-Cookie header value.
     */
    public function toSetCookieHeader(): string
    {
        $header = "{$this->name}={$this->value}";

        if ($this->expiresAt !== null) {
            $header .= '; Expires='.gmdate('D, d M Y H:i:s T', $this->expiresAt);
            $maxAge = $this->expiresAt - time();
            if ($maxAge > 0) {
                $header .= '; Max-Age='.$maxAge;
            }
        }

        if ($this->domain !== null) {
            $header .= '; Domain='.$this->domain;
        }

        if ($this->path !== '/') {
            $header .= '; Path='.$this->path;
        }

        if ($this->secure) {
            $header .= '; Secure';
        }

        if ($this->httpOnly) {
            $header .= '; HttpOnly';
        }

        if ($this->sameSite !== null) {
            $header .= '; SameSite='.$this->sameSite;
        }

        return $header;
    }

    /**
     * Convert cookie to Cookie header value (for requests).
     */
    public function toCookieHeader(): string
    {
        return "{$this->name}={$this->value}";
    }

    /**
     * Create a new cookie with updated value.
     */
    public function withValue(string $value): self
    {
        return new self(
            $this->name,
            $value,
            $this->expiresAt,
            $this->domain,
            $this->path,
            $this->secure,
            $this->httpOnly,
            $this->sameSite
        );
    }

    /**
     * Parse a Set-Cookie header into a Cookie instance.
     *
     * @param  string  $setCookieHeader  The Set-Cookie header value
     * @param  string  $defaultDomain  Default domain if not specified
     *
     * @throws \InvalidArgumentException If header is invalid
     */
    public static function fromSetCookieHeader(string $setCookieHeader, string $defaultDomain = ''): self
    {
        $parts = array_map('trim', explode(';', $setCookieHeader));

        if (empty($parts[0]) || ! str_contains($parts[0], '=')) {
            throw new \InvalidArgumentException('Invalid Set-Cookie header: missing name=value pair');
        }

        // Parse name=value
        [$name, $value] = array_pad(explode('=', $parts[0], 2), 2, '');

        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            throw new \InvalidArgumentException('Cookie name cannot be empty');
        }

        // Parse attributes
        $expiresAt = null;
        $domain = null;
        $path = '/';
        $secure = false;
        $httpOnly = false;
        $sameSite = null;

        foreach (array_slice($parts, 1) as $part) {
            if (stripos($part, 'expires=') === 0) {
                $dateStr = substr($part, 8);
                $timestamp = strtotime($dateStr);
                if ($timestamp !== false) {
                    $expiresAt = $timestamp;
                }
            } elseif (stripos($part, 'max-age=') === 0) {
                $maxAge = (int) substr($part, 8);
                $expiresAt = time() + $maxAge;
            } elseif (stripos($part, 'domain=') === 0) {
                $domain = substr($part, 7);
            } elseif (stripos($part, 'path=') === 0) {
                $path = substr($part, 5);
            } elseif (stripos($part, 'secure') === 0) {
                $secure = true;
            } elseif (stripos($part, 'httponly') === 0) {
                $httpOnly = true;
            } elseif (stripos($part, 'samesite=') === 0) {
                $sameSite = substr($part, 9);
            }
        }

        $domain = $domain ?: ($defaultDomain ?: null);

        return new self($name, $value, $expiresAt, $domain, $path, $secure, $httpOnly, $sameSite);
    }

    /**
     * Validate cookie name.
     *
     * @throws \InvalidArgumentException
     */
    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Cookie name cannot be empty');
        }

        // RFC 6265 cookie-name validation
        if (preg_match('/[()<>@,;:\\\\"\/\[\]?={} \t]/', $name)) {
            throw new \InvalidArgumentException("Invalid cookie name: {$name}");
        }
    }

    /**
     * Validate SameSite value.
     *
     * @throws \InvalidArgumentException
     */
    private function validateSameSite(?string $sameSite): void
    {
        if ($sameSite !== null && ! in_array($sameSite, ['Strict', 'Lax', 'None'], true)) {
            throw new \InvalidArgumentException("Invalid SameSite value: {$sameSite}");
        }
    }

    /**
     * Get unique identifier for this cookie (name + domain + path).
     */
    public function getIdentifier(): string
    {
        return sprintf('%s|%s|%s', $this->name, $this->domain ?? '', $this->path);
    }
}
