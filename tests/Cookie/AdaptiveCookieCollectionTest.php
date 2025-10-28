<?php

declare(strict_types=1);

use Farzai\Transport\Cookie\AdaptiveCookieCollection;
use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\IndexedCookieCollection;
use Farzai\Transport\Cookie\SimpleCookieCollection;

describe('AdaptiveCookieCollection', function () {
    // Initialization tests
    it('initializes with default threshold', function () {
        $collection = new AdaptiveCookieCollection;

        expect($collection->getThreshold())->toBe(50);
        expect($collection->isUpgraded())->toBeFalse();
        expect($collection->isEmpty())->toBeTrue();
    });

    it('initializes with custom threshold', function () {
        $collection = new AdaptiveCookieCollection(threshold: 100);

        expect($collection->getThreshold())->toBe(100);
        expect($collection->isUpgraded())->toBeFalse();
    });

    it('enforces minimum threshold of 1', function () {
        $collection = new AdaptiveCookieCollection(threshold: 0);

        expect($collection->getThreshold())->toBe(1);
    });

    it('initializes with SimpleCookieCollection by default', function () {
        $collection = new AdaptiveCookieCollection;

        expect($collection->getUnderlyingCollection())->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('accepts initial SimpleCookieCollection', function () {
        $simple = new SimpleCookieCollection;
        $simple->add(new Cookie('existing', 'value'));

        $collection = new AdaptiveCookieCollection(initialCollection: $simple);

        expect($collection->count())->toBe(1);
        expect($collection->isUpgraded())->toBeFalse();
    });

    it('accepts initial IndexedCookieCollection and marks as upgraded', function () {
        $indexed = new IndexedCookieCollection;
        $indexed->add(new Cookie('existing', 'value'));

        $collection = new AdaptiveCookieCollection(initialCollection: $indexed);

        expect($collection->count())->toBe(1);
        expect($collection->isUpgraded())->toBeTrue();
    });

    // Upgrade behavior tests
    it('starts as simple collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        expect($collection->getUnderlyingCollection())->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('stays simple below threshold', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        for ($i = 0; $i < 49; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }

        expect($collection->count())->toBe(49);
        expect($collection->isUpgraded())->toBeFalse();
        expect($collection->getUnderlyingCollection())->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('upgrades at threshold boundary', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        for ($i = 0; $i < 50; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }

        expect($collection->count())->toBe(50);
        expect($collection->isUpgraded())->toBeTrue();
        expect($collection->getUnderlyingCollection())->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('upgrades exactly when threshold is reached', function () {
        $collection = new AdaptiveCookieCollection(threshold: 10);

        // Add 9 cookies - should stay simple
        for ($i = 0; $i < 9; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }
        expect($collection->isUpgraded())->toBeFalse();

        // Add 10th cookie - should trigger upgrade
        $collection->add(new Cookie('cookie9', 'value9'));
        expect($collection->isUpgraded())->toBeTrue();
    });

    it('preserves all cookies during upgrade', function () {
        $collection = new AdaptiveCookieCollection(threshold: 5);

        $cookies = [];
        for ($i = 0; $i < 5; $i++) {
            $cookie = new Cookie("cookie{$i}", "value{$i}", null, 'example.com', "/path{$i}");
            $cookies[] = $cookie;
            $collection->add($cookie);
        }

        expect($collection->isUpgraded())->toBeTrue();
        expect($collection->count())->toBe(5);

        // Verify all cookies are still accessible
        foreach ($cookies as $cookie) {
            $retrieved = $collection->get($cookie->getIdentifier());
            expect($retrieved)->not->toBeNull();
            expect($retrieved->getValue())->toBe($cookie->getValue());
        }
    });

    it('stays upgraded after threshold is reached', function () {
        $collection = new AdaptiveCookieCollection(threshold: 5);

        for ($i = 0; $i < 10; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }

        expect($collection->isUpgraded())->toBeTrue();

        // Remove some cookies - should stay upgraded
        $collection->remove('cookie0|/');
        $collection->remove('cookie1|/');

        expect($collection->isUpgraded())->toBeTrue();
    });

    // Downgrade behavior tests
    it('downgrades after clear()', function () {
        $collection = new AdaptiveCookieCollection(threshold: 5);

        for ($i = 0; $i < 10; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }

        expect($collection->isUpgraded())->toBeTrue();

        $collection->clear();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isUpgraded())->toBeFalse();
        expect($collection->getUnderlyingCollection())->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('downgrades when removeExpired drops below 50% of threshold', function () {
        $collection = new AdaptiveCookieCollection(threshold: 100);

        // Add 100 cookies to trigger upgrade (90 expired, 10 valid)
        for ($i = 0; $i < 90; $i++) {
            $collection->add(new Cookie("expired{$i}", "value{$i}", time() - 3600));
        }
        for ($i = 0; $i < 10; $i++) {
            $collection->add(new Cookie("valid{$i}", "value{$i}", time() + 3600));
        }

        expect($collection->isUpgraded())->toBeTrue();

        // Remove expired cookies - should drop to 10 cookies (< 50 which is 50% of 100)
        $removed = $collection->removeExpired();

        expect($removed)->toBe(90);
        expect($collection->count())->toBe(10);
        expect($collection->isUpgraded())->toBeFalse();
    });

    it('stays upgraded when removeExpired keeps count above 50% threshold', function () {
        $collection = new AdaptiveCookieCollection(threshold: 100);

        // Add 100 cookies (40 expired, 60 valid)
        for ($i = 0; $i < 40; $i++) {
            $collection->add(new Cookie("expired{$i}", "value{$i}", time() - 3600));
        }
        for ($i = 0; $i < 60; $i++) {
            $collection->add(new Cookie("valid{$i}", "value{$i}", time() + 3600));
        }

        expect($collection->isUpgraded())->toBeTrue();

        // Remove expired - stays at 60 cookies (>= 50 which is 50% of 100)
        $collection->removeExpired();

        expect($collection->count())->toBe(60);
        expect($collection->isUpgraded())->toBeTrue();
    });

    // Force upgrade/downgrade tests
    it('force upgrades from simple to indexed', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        $collection->add(new Cookie('cookie1', 'value1'));
        $collection->add(new Cookie('cookie2', 'value2'));

        expect($collection->isUpgraded())->toBeFalse();

        $collection->forceUpgrade();

        expect($collection->isUpgraded())->toBeTrue();
        expect($collection->getUnderlyingCollection())->toBeInstanceOf(IndexedCookieCollection::class);
        expect($collection->count())->toBe(2);
    });

    it('force downgrade from indexed to simple', function () {
        $collection = new AdaptiveCookieCollection(threshold: 5);

        for ($i = 0; $i < 10; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}"));
        }

        expect($collection->isUpgraded())->toBeTrue();

        $collection->forceDowngrade();

        expect($collection->isUpgraded())->toBeFalse();
        expect($collection->getUnderlyingCollection())->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection->count())->toBe(10);
    });

    it('force upgrade does nothing if already upgraded', function () {
        $collection = new AdaptiveCookieCollection(threshold: 2);

        $collection->add(new Cookie('cookie1', 'value1'));
        $collection->add(new Cookie('cookie2', 'value2'));

        expect($collection->isUpgraded())->toBeTrue();

        $collection->forceUpgrade();

        expect($collection->isUpgraded())->toBeTrue();
    });

    it('force downgrade does nothing if already simple', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        $collection->add(new Cookie('cookie1', 'value1'));

        expect($collection->isUpgraded())->toBeFalse();

        $collection->forceDowngrade();

        expect($collection->isUpgraded())->toBeFalse();
    });

    // Delegation tests
    it('delegates add operation to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);
        $cookie = new Cookie('test', 'value');

        $collection->add($cookie);

        expect($collection->get($cookie->getIdentifier()))->toBe($cookie);
    });

    it('delegates remove operation to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);
        $cookie = new Cookie('test', 'value');

        $collection->add($cookie);
        $collection->remove($cookie->getIdentifier());

        expect($collection->get($cookie->getIdentifier()))->toBeNull();
    });

    it('delegates get operation to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);
        $cookie = new Cookie('test', 'value123');

        $collection->add($cookie);

        $retrieved = $collection->get($cookie->getIdentifier());
        expect($retrieved)->not->toBeNull();
        expect($retrieved->getValue())->toBe('value123');
    });

    it('delegates findForUrl to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);
        $cookie = new Cookie('session', 'abc', null, 'example.com', '/');

        $collection->add($cookie);

        $matches = $collection->findForUrl('https://example.com/', true);
        expect($matches)->toHaveCount(1);
        expect($matches[0]->getName())->toBe('session');
    });

    it('delegates all() to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);
        $cookie1 = new Cookie('first', 'value1');
        $cookie2 = new Cookie('second', 'value2');

        $collection->add($cookie1);
        $collection->add($cookie2);

        $all = $collection->all();
        expect($all)->toHaveCount(2);
    });

    it('delegates count() to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        expect($collection->count())->toBe(0);

        $collection->add(new Cookie('cookie1', 'value1'));
        expect($collection->count())->toBe(1);

        $collection->add(new Cookie('cookie2', 'value2'));
        expect($collection->count())->toBe(2);
    });

    it('delegates isEmpty() to underlying collection', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        expect($collection->isEmpty())->toBeTrue();

        $collection->add(new Cookie('test', 'value'));

        expect($collection->isEmpty())->toBeFalse();
    });

    // getType() tests
    it('returns type with Adaptive suffix when simple', function () {
        $collection = new AdaptiveCookieCollection(threshold: 50);

        expect($collection->getType())->toContain('SimpleCookieCollection');
        expect($collection->getType())->toContain('Adaptive');
    });

    it('returns type with Adaptive suffix when upgraded', function () {
        $collection = new AdaptiveCookieCollection(threshold: 2);

        $collection->add(new Cookie('cookie1', 'value1'));
        $collection->add(new Cookie('cookie2', 'value2'));

        expect($collection->getType())->toContain('IndexedCookieCollection');
        expect($collection->getType())->toContain('Adaptive');
    });

    // Edge cases and integration tests
    it('handles rapid additions across threshold', function () {
        $collection = new AdaptiveCookieCollection(threshold: 10);

        for ($i = 0; $i < 20; $i++) {
            $collection->add(new Cookie("rapid{$i}", "value{$i}"));
        }

        expect($collection->count())->toBe(20);
        expect($collection->isUpgraded())->toBeTrue();
    });

    it('maintains functionality after multiple upgrade/downgrade cycles', function () {
        $collection = new AdaptiveCookieCollection(threshold: 10);

        // Cycle 1: upgrade
        for ($i = 0; $i < 15; $i++) {
            $collection->add(new Cookie("cookie{$i}", "value{$i}", time() + 3600));
        }
        expect($collection->isUpgraded())->toBeTrue();

        // Cycle 2: clear and downgrade
        $collection->clear();
        expect($collection->isUpgraded())->toBeFalse();

        // Cycle 3: upgrade again
        for ($i = 0; $i < 12; $i++) {
            $collection->add(new Cookie("newcookie{$i}", "value{$i}"));
        }
        expect($collection->isUpgraded())->toBeTrue();

        expect($collection->count())->toBe(12);
    });

    it('correctly finds cookies after upgrade', function () {
        $collection = new AdaptiveCookieCollection(threshold: 3);

        $collection->add(new Cookie('cookie1', 'value1', null, 'example.com', '/'));
        $collection->add(new Cookie('cookie2', 'value2', null, 'example.com', '/api'));
        $collection->add(new Cookie('cookie3', 'value3', null, 'example.com', '/app'));

        expect($collection->isUpgraded())->toBeTrue();

        $matches = $collection->findForUrl('https://example.com/api/users', true);
        expect($matches)->toHaveCount(2); // /api and / match
    });

    it('handles cookies with various domains and paths across upgrade', function () {
        $collection = new AdaptiveCookieCollection(threshold: 5);

        $collection->add(new Cookie('root', 'val1', null, 'example.com', '/'));
        $collection->add(new Cookie('api', 'val2', null, 'api.example.com', '/'));
        $collection->add(new Cookie('shared', 'val3', null, '.example.com', '/'));
        $collection->add(new Cookie('secure', 'val4', null, 'example.com', '/secure', true));
        $collection->add(new Cookie('tracker', 'val5', null, '.example.com', '/track'));

        expect($collection->isUpgraded())->toBeTrue();
        expect($collection->count())->toBe(5);

        // Test various URL matches - only cookies that match domain AND path will be found
        $matches1 = $collection->findForUrl('https://example.com/', true);
        expect($matches1)->toHaveCount(2); // root (/) and shared (.example.com /)
        // Note: secure (/secure) doesn't match "/" and tracker (/track) doesn't match "/"

        $matches2 = $collection->findForUrl('https://api.example.com/', true);
        expect($matches2)->toHaveCount(2); // api, shared
    });

    it('preserves cookie order and sorting after upgrade', function () {
        $collection = new AdaptiveCookieCollection(threshold: 4);

        $collection->add(new Cookie('short', 'val1', null, 'example.com', '/'));
        $collection->add(new Cookie('medium', 'val2', null, 'example.com', '/api'));
        $collection->add(new Cookie('long', 'val3', null, 'example.com', '/api/users'));
        $collection->add(new Cookie('longest', 'val4', null, 'example.com', '/api/users/profile'));

        expect($collection->isUpgraded())->toBeTrue();

        $matches = $collection->findForUrl('https://example.com/api/users/profile', true);

        // Should be sorted by path length (longest first)
        expect($matches)->toHaveCount(4);
        expect($matches[0]->getName())->toBe('longest');
        expect($matches[1]->getName())->toBe('long');
        expect($matches[2]->getName())->toBe('medium');
        expect($matches[3]->getName())->toBe('short');
    });

    it('handles threshold of 1 correctly', function () {
        $collection = new AdaptiveCookieCollection(threshold: 1);

        expect($collection->isUpgraded())->toBeFalse();

        $collection->add(new Cookie('first', 'value'));

        expect($collection->isUpgraded())->toBeTrue();
    });

    it('replaces cookies with same identifier across upgrade', function () {
        $collection = new AdaptiveCookieCollection(threshold: 3);

        $cookie1 = new Cookie('token', 'old_value', null, 'example.com', '/');
        $collection->add($cookie1);
        $collection->add(new Cookie('other1', 'val1'));
        $collection->add(new Cookie('other2', 'val2'));

        expect($collection->isUpgraded())->toBeTrue();

        // Replace with same identifier
        $cookie2 = new Cookie('token', 'new_value', null, 'example.com', '/');
        $collection->add($cookie2);

        expect($collection->count())->toBe(3);
        expect($collection->get($cookie2->getIdentifier())->getValue())->toBe('new_value');
    });
});
