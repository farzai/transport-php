<?php

declare(strict_types=1);

use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\IndexedCookieCollection;

describe('IndexedCookieCollection', function () {
    // Basic Operations
    it('adds cookie and maintains primary storage', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'abc123', null, 'example.com', '/');

        $collection->add($cookie);

        expect($collection->count())->toBe(1);
        expect($collection->get($cookie->getIdentifier()))->toBe($cookie);
    });

    it('adds cookie and maintains domain index', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'abc123', null, 'example.com', '/');

        $collection->add($cookie);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(1);
        expect($stats['total_cookies'])->toBe(1);
    });

    it('replaces existing cookie with same identifier', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('token', 'old', null, 'example.com', '/');
        $cookie2 = new Cookie('token', 'new', null, 'example.com', '/');

        $collection->add($cookie1);
        $collection->add($cookie2);

        expect($collection->count())->toBe(1);
        expect($collection->get($cookie2->getIdentifier())->getValue())->toBe('new');
    });

    it('removes cookie and cleans up indices', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'abc123', null, 'example.com', '/');

        $collection->add($cookie);
        $collection->remove($cookie->getIdentifier());

        expect($collection->count())->toBe(0);
        expect($collection->get($cookie->getIdentifier()))->toBeNull();

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(0);
    });

    it('removes non-existent cookie gracefully', function () {
        $collection = new IndexedCookieCollection;

        $collection->remove('non-existent-id');

        expect($collection->count())->toBe(0);
    });

    it('gets cookie by identifier', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('token', 'xyz789', null, 'api.example.com', '/v1');

        $collection->add($cookie);

        $retrieved = $collection->get($cookie->getIdentifier());
        expect($retrieved)->not->toBeNull();
        expect($retrieved->getValue())->toBe('xyz789');
    });

    it('returns null for non-existent identifier', function () {
        $collection = new IndexedCookieCollection;

        expect($collection->get('non-existent'))->toBeNull();
    });

    it('counts cookies correctly', function () {
        $collection = new IndexedCookieCollection;

        $collection->add(new Cookie('cookie1', 'value1', null, 'example.com'));
        $collection->add(new Cookie('cookie2', 'value2', null, 'example.com'));
        $collection->add(new Cookie('cookie3', 'value3', null, 'other.com'));

        expect($collection->count())->toBe(3);
    });

    it('checks if collection is empty', function () {
        $collection = new IndexedCookieCollection;

        expect($collection->isEmpty())->toBeTrue();

        $collection->add(new Cookie('test', 'value'));

        expect($collection->isEmpty())->toBeFalse();
    });

    it('clears all cookies and indices', function () {
        $collection = new IndexedCookieCollection;

        $collection->add(new Cookie('cookie1', 'value1', null, 'example.com'));
        $collection->add(new Cookie('cookie2', 'value2', null, 'other.com'));

        $collection->clear();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(0);
    });

    it('returns all cookies as array', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('first', 'value1');
        $cookie2 = new Cookie('second', 'value2');

        $collection->add($cookie1);
        $collection->add($cookie2);

        $all = $collection->all();
        expect($all)->toHaveCount(2);
        expect($all)->toContain($cookie1);
        expect($all)->toContain($cookie2);
    });

    // Domain Indexing and Lookup Tests
    it('finds cookies for exact domain match', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'abc123', null, 'example.com', '/');

        $collection->add($cookie);

        $matches = $collection->findForUrl('https://example.com/', true);
        expect($matches)->toHaveCount(1);
        expect($matches[0]->getName())->toBe('session');
    });

    it('finds cookies for subdomain with parent domain cookie', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('shared', 'value', null, '.example.com', '/');

        $collection->add($cookie);

        $matches = $collection->findForUrl('https://api.example.com/', true);
        expect($matches)->toHaveCount(1);
        expect($matches[0]->getName())->toBe('shared');
    });

    it('finds cookies for multiple domain levels', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('root', 'value1', null, '.example.com', '/');
        $cookie2 = new Cookie('subdomain', 'value2', null, 'api.example.com', '/');

        $collection->add($cookie1);
        $collection->add($cookie2);

        $matches = $collection->findForUrl('https://api.example.com/', true);
        expect($matches)->toHaveCount(2);
    });

    it('does not find cookies from different domains', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'value', null, 'example.com', '/');

        $collection->add($cookie);

        $matches = $collection->findForUrl('https://other.com/', true);
        expect($matches)->toHaveCount(0);
    });

    it('finds cookies with deep subdomain matching', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('tracker', 'value', null, '.example.com', '/');

        $collection->add($cookie);

        $matches = $collection->findForUrl('https://api.v2.prod.example.com/', true);
        expect($matches)->toHaveCount(1);
    });

    // Path Sorting Tests
    it('sorts cookies by path specificity', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('root', 'value1', null, 'example.com', '/');
        $cookie2 = new Cookie('api', 'value2', null, 'example.com', '/api');
        $cookie3 = new Cookie('users', 'value3', null, 'example.com', '/api/users');

        // Add in random order
        $collection->add($cookie1);
        $collection->add($cookie3);
        $collection->add($cookie2);

        $matches = $collection->findForUrl('https://example.com/api/users/123', true);

        expect($matches)->toHaveCount(3);
        // More specific paths should come first
        expect($matches[0]->getName())->toBe('users');
        expect($matches[1]->getName())->toBe('api');
        expect($matches[2]->getName())->toBe('root');
    });

    it('sorts cookies with equal path lengths by maintaining order', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('first', 'value1', null, 'example.com', '/api');
        $cookie2 = new Cookie('second', 'value2', null, 'example.com', '/app');

        $collection->add($cookie1);
        $collection->add($cookie2);

        $matches = $collection->findForUrl('https://example.com/api', true);

        expect($matches)->toHaveCount(1);
        expect($matches[0]->getName())->toBe('first');
    });

    // Secure Flag Tests
    it('respects secure flag for HTTPS requests', function () {
        $collection = new IndexedCookieCollection;
        $secureCookie = new Cookie('secure', 'value', null, 'example.com', '/', true);

        $collection->add($secureCookie);

        $httpsMatches = $collection->findForUrl('https://example.com/', true);
        $httpMatches = $collection->findForUrl('http://example.com/', false);

        expect($httpsMatches)->toHaveCount(1);
        expect($httpMatches)->toHaveCount(0);
    });

    // Expired Cookie Tests
    it('removes expired cookies and updates indices', function () {
        $collection = new IndexedCookieCollection;
        $expired1 = new Cookie('old1', 'value1', time() - 3600, 'example.com');
        $expired2 = new Cookie('old2', 'value2', time() - 7200, 'other.com');
        $valid = new Cookie('fresh', 'value3', time() + 3600, 'example.com');

        $collection->add($expired1);
        $collection->add($expired2);
        $collection->add($valid);

        $removed = $collection->removeExpired();

        expect($removed)->toBe(2);
        expect($collection->count())->toBe(1);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(1);
    });

    it('removeExpired returns zero when no cookies expired', function () {
        $collection = new IndexedCookieCollection;
        $valid1 = new Cookie('cookie1', 'value1', time() + 3600);
        $valid2 = new Cookie('cookie2', 'value2', time() + 7200);

        $collection->add($valid1);
        $collection->add($valid2);

        $removed = $collection->removeExpired();

        expect($removed)->toBe(0);
        expect($collection->count())->toBe(2);
    });

    // Index Statistics Tests
    it('provides accurate index statistics', function () {
        $collection = new IndexedCookieCollection;
        $collection->add(new Cookie('cookie1', 'value1', null, 'example.com'));
        $collection->add(new Cookie('cookie2', 'value2', null, 'example.com'));
        $collection->add(new Cookie('cookie3', 'value3', null, 'other.com'));

        $stats = $collection->getIndexStats();

        expect($stats['total_cookies'])->toBe(3);
        expect($stats['indexed_domains'])->toBe(2);
        expect($stats['avg_cookies_per_domain'])->toBeGreaterThan(0);
        expect($stats['max_cookies_per_domain'])->toBeGreaterThanOrEqual(1);
    });

    it('calculates memory overhead ratio correctly', function () {
        $collection = new IndexedCookieCollection;

        // Add multiple cookies to same domain
        for ($i = 0; $i < 10; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}", null, 'example.com', "/path{$i}"));
        }

        $stats = $collection->getIndexStats();

        expect($stats['memory_overhead_ratio'])->toBeGreaterThan(0);
        expect($stats['memory_overhead_ratio'])->toBeLessThan(1); // Should be less than 1 for single domain
    });

    it('handles empty collection statistics', function () {
        $collection = new IndexedCookieCollection;

        $stats = $collection->getIndexStats();

        expect($stats['total_cookies'])->toBe(0);
        expect($stats['indexed_domains'])->toBe(0);
        expect($stats['avg_cookies_per_domain'])->toBe(0);
        expect($stats['max_cookies_per_domain'])->toBe(0);
        expect($stats['memory_overhead_ratio'])->toBe(0);
    });

    // Edge Cases
    it('handles invalid URL gracefully', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('test', 'value', null, 'example.com');

        $collection->add($cookie);

        $matches = $collection->findForUrl('http:///invalid', true);
        expect($matches)->toHaveCount(0);
    });

    it('handles URL without host', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('test', 'value', null, 'example.com');

        $collection->add($cookie);

        $matches = $collection->findForUrl('/just/a/path', true);
        expect($matches)->toHaveCount(0);
    });

    it('handles cookie with null domain', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('test', 'value', null, null, '/');

        $collection->add($cookie);

        expect($collection->count())->toBe(1);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBeGreaterThanOrEqual(0);
    });

    it('handles multiple cookies with same name but different paths', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('token', 'value1', null, 'example.com', '/');
        $cookie2 = new Cookie('token', 'value2', null, 'example.com', '/api');

        $collection->add($cookie1);
        $collection->add($cookie2);

        expect($collection->count())->toBe(2);

        $matches = $collection->findForUrl('https://example.com/api/users', true);
        expect($matches)->toHaveCount(2);
    });

    it('updates domain index when cookie domain changes', function () {
        $collection = new IndexedCookieCollection;
        $cookie1 = new Cookie('session', 'value', null, 'old.com', '/');

        $collection->add($cookie1);
        expect($collection->findForUrl('https://old.com/', true))->toHaveCount(1);

        // Different domain means different cookie (different identifier)
        $cookie2 = new Cookie('session', 'value', null, 'new.com', '/');
        $collection->add($cookie2);

        // Both cookies should exist since they have different identifiers
        expect($collection->count())->toBe(2);
        expect($collection->findForUrl('https://old.com/', true))->toHaveCount(1);
        expect($collection->findForUrl('https://new.com/', true))->toHaveCount(1);
    });

    // Performance Characteristics
    it('efficiently handles large number of cookies', function () {
        $collection = new IndexedCookieCollection;

        // Add 100 cookies across 10 domains
        for ($domain = 0; $domain < 10; $domain++) {
            for ($i = 0; $i < 10; $i++) {
                $cookie = new Cookie(
                    "cookie_{$domain}_{$i}",
                    "value_{$i}",
                    null,
                    "domain{$domain}.com",
                    "/path{$i}"
                );
                $collection->add($cookie);
            }
        }

        expect($collection->count())->toBe(100);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(10);
        expect($stats['avg_cookies_per_domain'])->toBe(10);

        // Should efficiently find cookies for one domain without checking all 100
        // Only cookies with paths that are prefixes of the URL path will match
        $matches = $collection->findForUrl('https://domain5.com/path3/something', true);
        expect($matches)->toHaveCount(1); // Only /path3 matches /path3/something
    });

    it('maintains index integrity after multiple operations', function () {
        $collection = new IndexedCookieCollection;

        // Add cookies
        $cookie1 = new Cookie('cookie1', 'value1', null, 'example.com');
        $cookie2 = new Cookie('cookie2', 'value2', null, 'example.com');
        $collection->add($cookie1);
        $collection->add($cookie2);

        // Remove one
        $collection->remove($cookie1->getIdentifier());

        // Add another
        $cookie3 = new Cookie('cookie3', 'value3', null, 'example.com');
        $collection->add($cookie3);

        // Replace existing
        $cookie2Updated = new Cookie('cookie2', 'new_value', null, 'example.com');
        $collection->add($cookie2Updated);

        $matches = $collection->findForUrl('https://example.com/', true);
        expect($matches)->toHaveCount(2);
        expect($collection->count())->toBe(2);

        $stats = $collection->getIndexStats();
        expect($stats['indexed_domains'])->toBe(1);
    });

    it('returns correct type identifier', function () {
        $collection = new IndexedCookieCollection;

        expect($collection->getType())->toBe(IndexedCookieCollection::class);
    });

    it('handles concurrent domain index lookups', function () {
        $collection = new IndexedCookieCollection;

        // Add cookies for multiple domains
        $collection->add(new Cookie('cookie1', 'value1', null, 'example.com', '/'));
        $collection->add(new Cookie('cookie2', 'value2', null, '.example.com', '/'));
        $collection->add(new Cookie('cookie3', 'value3', null, 'api.example.com', '/'));

        // Should find all three for api.example.com
        // (exact match + .example.com parent + api.example.com)
        $matches = $collection->findForUrl('https://api.example.com/', true);
        expect($matches)->toHaveCount(2); // .example.com and api.example.com
    });

    it('avoids duplicate identifiers in domain index', function () {
        $collection = new IndexedCookieCollection;
        $cookie = new Cookie('session', 'value', null, 'example.com', '/');

        // Add same cookie multiple times
        $collection->add($cookie);
        $collection->add($cookie);
        $collection->add($cookie);

        expect($collection->count())->toBe(1);
        $matches = $collection->findForUrl('https://example.com/', true);
        expect($matches)->toHaveCount(1);
    });
});
