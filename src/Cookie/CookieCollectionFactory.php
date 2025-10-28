<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Factory for creating cookie collections with automatic strategy selection.
 *
 * Design Pattern: Factory Pattern + Strategy Pattern
 * - Creates appropriate collection based on cookie count
 * - Encapsulates strategy selection logic
 * - Provides consistent interface
 *
 * Selection Strategy:
 * - < 50 cookies: SimpleCookieCollection (linear search, lower overhead)
 * - >= 50 cookies: IndexedCookieCollection (hash map, faster lookups)
 *
 * Rationale for 50 cookie threshold:
 * - Benchmarks show indexed lookup becomes beneficial at ~50 cookies
 * - Below 50: Overhead of indexing outweighs benefits
 * - Above 50: O(1) lookups significantly faster than O(n)
 *
 * @example
 * ```php
 * // Auto-select based on count
 * $collection = CookieCollectionFactory::create(45); // SimpleCookieCollection
 * $collection = CookieCollectionFactory::create(100); // IndexedCookieCollection
 *
 * // Force specific implementation
 * $collection = CookieCollectionFactory::createSimple();
 * $collection = CookieCollectionFactory::createIndexed();
 *
 * // With custom threshold
 * $collection = CookieCollectionFactory::create(75, threshold: 100);
 * ```
 */
final class CookieCollectionFactory
{
    /**
     * Default threshold for switching to indexed collection (50 cookies).
     *
     * Based on performance benchmarks showing indexed lookup
     * becomes beneficial around 50 cookies.
     */
    public const DEFAULT_THRESHOLD = 50;

    /**
     * Create a cookie collection, auto-selecting implementation based on expected count.
     *
     * @param  int  $expectedCount  Expected number of cookies
     * @param  int  $threshold  Cookie count threshold for indexed collection
     * @return CookieCollectionInterface The cookie collection
     */
    public static function create(
        int $expectedCount = 0,
        int $threshold = self::DEFAULT_THRESHOLD
    ): CookieCollectionInterface {
        if ($expectedCount >= $threshold) {
            return new IndexedCookieCollection;
        }

        return new SimpleCookieCollection;
    }

    /**
     * Create a simple (non-indexed) cookie collection.
     *
     * Use for small cookie counts or when memory is more critical than speed.
     */
    public static function createSimple(): SimpleCookieCollection
    {
        return new SimpleCookieCollection;
    }

    /**
     * Create an indexed cookie collection.
     *
     * Use for large cookie counts or when lookup speed is critical.
     */
    public static function createIndexed(): IndexedCookieCollection
    {
        return new IndexedCookieCollection;
    }

    /**
     * Get the recommended collection type for a given cookie count.
     *
     * @param  int  $count  Cookie count
     * @param  int  $threshold  Threshold for indexed collection
     * @return class-string<CookieCollectionInterface> Collection class name
     */
    public static function getRecommendedType(
        int $count,
        int $threshold = self::DEFAULT_THRESHOLD
    ): string {
        if ($count >= $threshold) {
            return IndexedCookieCollection::class;
        }

        return SimpleCookieCollection::class;
    }

    /**
     * Check if indexed collection is recommended for a given count.
     *
     * @param  int  $count  Cookie count
     * @param  int  $threshold  Threshold for indexed collection
     * @return bool True if indexed recommended, false otherwise
     */
    public static function shouldUseIndexed(
        int $count,
        int $threshold = self::DEFAULT_THRESHOLD
    ): bool {
        return $count >= $threshold;
    }

    /**
     * Create a collection from an existing array of cookies.
     *
     * Automatically selects implementation based on cookie count.
     *
     * @param  array<Cookie>  $cookies  Array of cookies
     * @param  int  $threshold  Threshold for indexed collection
     * @return CookieCollectionInterface The cookie collection
     */
    public static function fromCookies(
        array $cookies,
        int $threshold = self::DEFAULT_THRESHOLD
    ): CookieCollectionInterface {
        $collection = self::create(count($cookies), $threshold);

        foreach ($cookies as $cookie) {
            $collection->add($cookie);
        }

        return $collection;
    }

    /**
     * Create a collection and automatically upgrade when threshold is reached.
     *
     * Returns an adaptive collection that starts as simple and upgrades
     * to indexed when it crosses the threshold.
     *
     * Note: This requires the collection to be wrapped in an adapter.
     *
     * @param  int  $threshold  Threshold for upgrade
     * @return AdaptiveCookieCollection Adaptive collection wrapper
     */
    public static function createAdaptive(
        int $threshold = self::DEFAULT_THRESHOLD
    ): AdaptiveCookieCollection {
        return new AdaptiveCookieCollection($threshold);
    }

    /**
     * Get the default threshold.
     *
     * @return int Default threshold (50)
     */
    public static function getDefaultThreshold(): int
    {
        return self::DEFAULT_THRESHOLD;
    }

    /**
     * Migrate from one collection type to another.
     *
     * Useful when manually upgrading/downgrading collections.
     *
     * @param  CookieCollectionInterface  $from  Source collection
     * @param  class-string<CookieCollectionInterface>  $toType  Target collection class
     * @return CookieCollectionInterface New collection with same cookies
     */
    public static function migrate(
        CookieCollectionInterface $from,
        string $toType
    ): CookieCollectionInterface {
        if ($from instanceof $toType) {
            return $from; // Already correct type
        }

        $to = new $toType;

        foreach ($from->all() as $cookie) {
            $to->add($cookie);
        }

        return $to;
    }
}
