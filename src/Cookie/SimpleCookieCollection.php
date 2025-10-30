<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Simple cookie collection using linear search.
 *
 * Design Pattern: Composite Pattern (Leaf)
 * - Implements CookieCollectionInterface
 * - Uses array with linear search (O(n))
 * - Best for small cookie counts (< 50 cookies)
 *
 * Characteristics:
 * - Time Complexity: O(n) for findForUrl()
 * - Space Complexity: O(n)
 * - Memory Overhead: Minimal (single array)
 * - Best Use: Small cookie counts, simple scenarios
 *
 * @example
 * ```php
 * $collection = new SimpleCookieCollection();
 * $collection->add(new Cookie('session', 'abc123', null, 'example.com'));
 * $matches = $collection->findForUrl('https://example.com/path');
 * ```
 */
final class SimpleCookieCollection implements CookieCollectionInterface
{
    /**
     * @var array<string, Cookie>
     */
    private array $cookies = [];

    /**
     * {@inheritDoc}
     */
    public function add(Cookie $cookie): void
    {
        $this->cookies[$cookie->getIdentifier()] = $cookie;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $identifier): void
    {
        unset($this->cookies[$identifier]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $identifier): ?Cookie
    {
        return $this->cookies[$identifier] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function findForUrl(string $url, bool $isSecure): array
    {
        $matching = [];

        // Linear search through all cookies - O(n)
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
                return 0;
            }

            // Longer paths first (more specific)
            return $lengthB <=> $lengthA;
        });

        return $matching;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return array_values($this->cookies);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->cookies);
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->cookies);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->cookies = [];
    }

    /**
     * {@inheritDoc}
     */
    public function removeExpired(): int
    {
        $originalCount = count($this->cookies);

        $this->cookies = array_filter(
            $this->cookies,
            fn (Cookie $cookie) => ! $cookie->isExpired()
        );

        return $originalCount - count($this->cookies);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return self::class;
    }
}
