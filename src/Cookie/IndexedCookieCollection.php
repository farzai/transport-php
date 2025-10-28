<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Indexed cookie collection using hash map for fast domain lookups.
 *
 * Design Pattern: Composite Pattern (Leaf) + Index Pattern
 * - Implements CookieCollectionInterface
 * - Uses hash map indexed by domain (O(1) average case)
 * - Best for large cookie counts (>= 50 cookies)
 *
 * Characteristics:
 * - Time Complexity: O(1) average case for findForUrl() (per domain)
 * - Space Complexity: O(n) with indexing overhead
 * - Memory Overhead: ~2x (maintains both identifier map and domain index)
 * - Best Use: Large cookie counts, high-traffic scenarios
 *
 * Indexing Strategy:
 * - Primary index: identifier → Cookie
 * - Secondary index: domain → Cookie[]
 * - Handles subdomain matching efficiently
 *
 * @example
 * ```php
 * $collection = new IndexedCookieCollection();
 * $collection->add(new Cookie('session', 'abc123', null, 'example.com'));
 * $matches = $collection->findForUrl('https://example.com/path');
 * // O(1) domain lookup instead of O(n) linear search
 * ```
 */
final class IndexedCookieCollection implements CookieCollectionInterface
{
    /**
     * Primary storage: identifier → Cookie.
     *
     * @var array<string, Cookie>
     */
    private array $cookies = [];

    /**
     * Secondary index: domain → array of cookie identifiers.
     *
     * Example:
     * [
     *     'example.com' => ['session|example.com|/', 'token|example.com|/'],
     *     '.example.com' => ['ga|.example.com|/'],
     * ]
     *
     * @var array<string, array<string>>
     */
    private array $domainIndex = [];

    /**
     * {@inheritDoc}
     */
    public function add(Cookie $cookie): void
    {
        $identifier = $cookie->getIdentifier();
        $domain = $cookie->getDomain() ?? '';

        // Remove old cookie if exists (to update index)
        if (isset($this->cookies[$identifier])) {
            $this->removeFromDomainIndex($identifier, $domain);
        }

        // Add to primary storage
        $this->cookies[$identifier] = $cookie;

        // Add to domain index
        $this->addToDomainIndex($identifier, $domain);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $identifier): void
    {
        if (! isset($this->cookies[$identifier])) {
            return;
        }

        $cookie = $this->cookies[$identifier];
        $domain = $cookie->getDomain() ?? '';

        // Remove from domain index
        $this->removeFromDomainIndex($identifier, $domain);

        // Remove from primary storage
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
     *
     * This is the optimized method that benefits from domain indexing.
     * Instead of checking ALL cookies, we only check cookies for relevant domains.
     */
    public function findForUrl(string $url, bool $isSecure): array
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return [];
        }

        $domain = $parsed['host'] ?? '';
        if ($domain === '') {
            return [];
        }

        // Get candidate cookies from domain index - O(1) lookup
        $candidates = $this->getCandidateCookies($domain);

        // Filter candidates that actually match the URL
        $matching = [];
        foreach ($candidates as $cookie) {
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
        $this->domainIndex = [];
    }

    /**
     * {@inheritDoc}
     */
    public function removeExpired(): int
    {
        $originalCount = count($this->cookies);
        $toRemove = [];

        // Find expired cookies
        foreach ($this->cookies as $identifier => $cookie) {
            if ($cookie->isExpired()) {
                $toRemove[] = $identifier;
            }
        }

        // Remove them (this updates indexes)
        foreach ($toRemove as $identifier) {
            $this->remove($identifier);
        }

        return count($toRemove);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return self::class;
    }

    /**
     * Get candidate cookies for a domain.
     *
     * Returns cookies that might match the domain, including:
     * - Exact domain match
     * - Parent domain matches (.example.com matches api.example.com)
     *
     * @param  string  $domain  The domain to match
     * @return array<Cookie> Candidate cookies
     */
    private function getCandidateCookies(string $domain): array
    {
        $candidates = [];

        // Check exact domain
        if (isset($this->domainIndex[$domain])) {
            foreach ($this->domainIndex[$domain] as $identifier) {
                if (isset($this->cookies[$identifier])) {
                    $candidates[] = $this->cookies[$identifier];
                }
            }
        }

        // Check domain with leading dot (.example.com)
        $dottedDomain = '.'.$domain;
        if (isset($this->domainIndex[$dottedDomain])) {
            foreach ($this->domainIndex[$dottedDomain] as $identifier) {
                if (isset($this->cookies[$identifier])) {
                    $candidates[] = $this->cookies[$identifier];
                }
            }
        }

        // Check parent domains (e.g., .example.com for api.example.com)
        $parts = explode('.', $domain);
        for ($i = 1; $i < count($parts); $i++) {
            $parentDomain = '.'.implode('.', array_slice($parts, $i));

            if (isset($this->domainIndex[$parentDomain])) {
                foreach ($this->domainIndex[$parentDomain] as $identifier) {
                    if (isset($this->cookies[$identifier])) {
                        $candidates[] = $this->cookies[$identifier];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Add a cookie identifier to the domain index.
     *
     * @param  string  $identifier  Cookie identifier
     * @param  string  $domain  Cookie domain
     */
    private function addToDomainIndex(string $identifier, string $domain): void
    {
        if (! isset($this->domainIndex[$domain])) {
            $this->domainIndex[$domain] = [];
        }

        // Avoid duplicates
        if (! in_array($identifier, $this->domainIndex[$domain], true)) {
            $this->domainIndex[$domain][] = $identifier;
        }
    }

    /**
     * Remove a cookie identifier from the domain index.
     *
     * @param  string  $identifier  Cookie identifier
     * @param  string  $domain  Cookie domain
     */
    private function removeFromDomainIndex(string $identifier, string $domain): void
    {
        if (! isset($this->domainIndex[$domain])) {
            return;
        }

        $this->domainIndex[$domain] = array_filter(
            $this->domainIndex[$domain],
            fn ($id) => $id !== $identifier
        );

        // Clean up empty domain entries
        if (empty($this->domainIndex[$domain])) {
            unset($this->domainIndex[$domain]);
        }
    }

    /**
     * Get statistics about the index (for debugging/monitoring).
     *
     * @return array<string, mixed> Index statistics
     */
    public function getIndexStats(): array
    {
        $domainCounts = array_map('count', $this->domainIndex);

        return [
            'total_cookies' => count($this->cookies),
            'indexed_domains' => count($this->domainIndex),
            'avg_cookies_per_domain' => count($this->cookies) > 0
                ? count($this->cookies) / max(1, count($this->domainIndex))
                : 0,
            'max_cookies_per_domain' => ! empty($domainCounts) ? max($domainCounts) : 0,
            'memory_overhead_ratio' => count($this->cookies) > 0
                ? count($this->domainIndex) / count($this->cookies)
                : 0,
        ];
    }
}
