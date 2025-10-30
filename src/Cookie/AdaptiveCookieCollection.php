<?php

declare(strict_types=1);

namespace Farzai\Transport\Cookie;

/**
 * Adaptive cookie collection that automatically upgrades from simple to indexed.
 *
 * Design Pattern: Adapter Pattern + Strategy Pattern
 * - Wraps underlying collection implementation
 * - Automatically switches strategy when threshold reached
 * - Provides transparent upgrade without user intervention
 *
 * Lifecycle:
 * 1. Starts as SimpleCookieCollection (low overhead)
 * 2. Monitors cookie count on each add()
 * 3. Upgrades to IndexedCookieCollection when threshold reached
 * 4. All subsequent operations use indexed collection
 *
 * Use Case:
 * - When cookie count is unpredictable
 * - When you want automatic optimization
 * - When you want to start light and scale up
 *
 * @example
 * ```php
 * $collection = new AdaptiveCookieCollection(threshold: 50);
 *
 * // Starts as SimpleCookieCollection
 * for ($i = 0; $i < 45; $i++) {
 *     $collection->add($cookie); // Still simple
 * }
 *
 * // Auto-upgrades to IndexedCookieCollection
 * $collection->add($cookie); // Triggers upgrade at cookie #50
 *
 * // Now using indexed collection
 * $matches = $collection->findForUrl($url); // O(1) lookup
 * ```
 */
final class AdaptiveCookieCollection implements CookieCollectionInterface
{
    /**
     * Underlying collection implementation.
     */
    private CookieCollectionInterface $collection;

    /**
     * Threshold for upgrading to indexed collection.
     */
    private int $threshold;

    /**
     * Whether collection has been upgraded.
     */
    private bool $upgraded = false;

    /**
     * Create a new adaptive cookie collection.
     *
     * @param  int  $threshold  Cookie count threshold for upgrade (default 50)
     * @param  CookieCollectionInterface|null  $initialCollection  Optional initial collection
     */
    public function __construct(
        int $threshold = CookieCollectionFactory::DEFAULT_THRESHOLD,
        ?CookieCollectionInterface $initialCollection = null
    ) {
        $this->threshold = max(1, $threshold);
        $this->collection = $initialCollection ?? new SimpleCookieCollection;

        // Check if initial collection is already indexed
        $this->upgraded = $this->collection instanceof IndexedCookieCollection;
    }

    /**
     * {@inheritDoc}
     */
    public function add(Cookie $cookie): void
    {
        $this->collection->add($cookie);

        // Check if we should upgrade
        $this->maybeUpgrade();
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $identifier): void
    {
        $this->collection->remove($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $identifier): ?Cookie
    {
        return $this->collection->get($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function findForUrl(string $url, bool $isSecure): array
    {
        return $this->collection->findForUrl($url, $isSecure);
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->collection->all();
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return $this->collection->count();
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return $this->collection->isEmpty();
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->collection->clear();

        // Downgrade back to simple after clear
        if ($this->upgraded) {
            $this->collection = new SimpleCookieCollection;
            $this->upgraded = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removeExpired(): int
    {
        $removed = $this->collection->removeExpired();

        // Check if we should downgrade after removing cookies
        $this->maybeDowngrade();

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->collection->getType().' (Adaptive)';
    }

    /**
     * Check if the collection has been upgraded.
     *
     * @return bool True if upgraded to indexed, false if still simple
     */
    public function isUpgraded(): bool
    {
        return $this->upgraded;
    }

    /**
     * Get the current threshold.
     *
     * @return int Threshold value
     */
    public function getThreshold(): int
    {
        return $this->threshold;
    }

    /**
     * Get the underlying collection implementation.
     *
     * @return CookieCollectionInterface The wrapped collection
     */
    public function getUnderlyingCollection(): CookieCollectionInterface
    {
        return $this->collection;
    }

    /**
     * Force upgrade to indexed collection.
     *
     * Useful for testing or when you know you'll need indexed performance.
     */
    public function forceUpgrade(): void
    {
        if ($this->upgraded) {
            return; // Already upgraded
        }

        $this->upgrade();
    }

    /**
     * Force downgrade to simple collection.
     *
     * Useful when cookie count decreases significantly.
     */
    public function forceDowngrade(): void
    {
        if (! $this->upgraded) {
            return; // Already simple
        }

        $this->downgrade();
    }

    /**
     * Check if upgrade is needed and perform it.
     */
    private function maybeUpgrade(): void
    {
        if ($this->upgraded) {
            return; // Already upgraded
        }

        if ($this->collection->count() >= $this->threshold) {
            $this->upgrade();
        }
    }

    /**
     * Check if downgrade is beneficial and perform it.
     *
     * Downgrades if cookie count drops significantly below threshold (< 50% of threshold).
     */
    private function maybeDowngrade(): void
    {
        if (! $this->upgraded) {
            return; // Already simple
        }

        // Downgrade if count drops below 50% of threshold
        $downgradeThreshold = (int) ($this->threshold * 0.5);
        if ($this->collection->count() < $downgradeThreshold) {
            $this->downgrade();
        }
    }

    /**
     * Upgrade from simple to indexed collection.
     */
    private function upgrade(): void
    {
        // Create new indexed collection
        $indexed = new IndexedCookieCollection;

        // Migrate all cookies
        foreach ($this->collection->all() as $cookie) {
            $indexed->add($cookie);
        }

        // Switch to indexed collection
        $this->collection = $indexed;
        $this->upgraded = true;
    }

    /**
     * Downgrade from indexed to simple collection.
     */
    private function downgrade(): void
    {
        // Create new simple collection
        $simple = new SimpleCookieCollection;

        // Migrate all cookies
        foreach ($this->collection->all() as $cookie) {
            $simple->add($cookie);
        }

        // Switch to simple collection
        $this->collection = $simple;
        $this->upgraded = false;
    }
}
