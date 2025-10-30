<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Interface for cookie collection storage strategies.
 *
 * Design Pattern: Composite Pattern + Strategy Pattern
 * - Defines common interface for different storage implementations
 * - Allows transparent switching between simple and indexed storage
 * - Enables performance optimization based on cookie count
 *
 * Implementations:
 * - SimpleCookieCollection: Linear search (O(n)) - Good for < 50 cookies
 * - IndexedCookieCollection: Hash map lookup (O(1)) - Good for >= 50 cookies
 *
 * @example
 * ```php
 * $collection = CookieCollectionFactory::create();
 * $collection->add($cookie);
 * $matches = $collection->findForUrl('https://example.com');
 * ```
 */
interface CookieCollectionInterface
{
    /**
     * Add a cookie to the collection.
     *
     * If a cookie with the same identifier exists, it will be replaced.
     *
     * @param  Cookie  $cookie  The cookie to add
     */
    public function add(Cookie $cookie): void;

    /**
     * Remove a cookie by its identifier.
     *
     * @param  string  $identifier  The cookie identifier (name|domain|path)
     */
    public function remove(string $identifier): void;

    /**
     * Get a cookie by its identifier.
     *
     * @param  string  $identifier  The cookie identifier (name|domain|path)
     * @return Cookie|null The cookie if found, null otherwise
     */
    public function get(string $identifier): ?Cookie;

    /**
     * Find all cookies matching a URL.
     *
     * This is the performance-critical method that benefits from indexing.
     *
     * @param  string  $url  The URL to match
     * @param  bool  $isSecure  Whether the request is HTTPS
     * @return array<Cookie> Array of matching cookies
     */
    public function findForUrl(string $url, bool $isSecure): array;

    /**
     * Get all cookies in the collection.
     *
     * @return array<Cookie> Array of all cookies
     */
    public function all(): array;

    /**
     * Get the number of cookies in the collection.
     *
     * @return int Cookie count
     */
    public function count(): int;

    /**
     * Check if the collection is empty.
     *
     * @return bool True if empty, false otherwise
     */
    public function isEmpty(): bool;

    /**
     * Remove all cookies from the collection.
     */
    public function clear(): void;

    /**
     * Remove all expired cookies.
     *
     * @return int Number of cookies removed
     */
    public function removeExpired(): int;

    /**
     * Get the implementation type (for debugging/stats).
     *
     * @return string Implementation class name
     */
    public function getType(): string;
}
