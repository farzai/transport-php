<?php

declare(strict_types=1);

use Farzai\Transport\Cookie\AdaptiveCookieCollection;
use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\CookieCollectionFactory;
use Farzai\Transport\Cookie\IndexedCookieCollection;
use Farzai\Transport\Cookie\SimpleCookieCollection;

describe('CookieCollectionFactory', function () {
    // create() method tests
    it('creates SimpleCookieCollection for count below threshold', function () {
        $collection = CookieCollectionFactory::create(30);

        expect($collection)->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('creates IndexedCookieCollection for count at threshold', function () {
        $collection = CookieCollectionFactory::create(50);

        expect($collection)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('creates IndexedCookieCollection for count above threshold', function () {
        $collection = CookieCollectionFactory::create(100);

        expect($collection)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('creates SimpleCookieCollection for zero count', function () {
        $collection = CookieCollectionFactory::create(0);

        expect($collection)->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('respects custom threshold in create()', function () {
        $collection1 = CookieCollectionFactory::create(75, threshold: 100);
        $collection2 = CookieCollectionFactory::create(75, threshold: 50);

        expect($collection1)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection2)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('handles boundary case at custom threshold', function () {
        $customThreshold = 25;

        $belowThreshold = CookieCollectionFactory::create(24, threshold: $customThreshold);
        $atThreshold = CookieCollectionFactory::create(25, threshold: $customThreshold);

        expect($belowThreshold)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($atThreshold)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    // createSimple() method tests
    it('creates SimpleCookieCollection via createSimple()', function () {
        $collection = CookieCollectionFactory::createSimple();

        expect($collection)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection->isEmpty())->toBeTrue();
    });

    // createIndexed() method tests
    it('creates IndexedCookieCollection via createIndexed()', function () {
        $collection = CookieCollectionFactory::createIndexed();

        expect($collection)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($collection->isEmpty())->toBeTrue();
    });

    // getRecommendedType() method tests
    it('recommends SimpleCookieCollection for small count', function () {
        $type = CookieCollectionFactory::getRecommendedType(20);

        expect($type)->toBe(SimpleCookieCollection::class);
    });

    it('recommends IndexedCookieCollection for large count', function () {
        $type = CookieCollectionFactory::getRecommendedType(80);

        expect($type)->toBe(IndexedCookieCollection::class);
    });

    it('recommends IndexedCookieCollection at exact threshold', function () {
        $type = CookieCollectionFactory::getRecommendedType(50);

        expect($type)->toBe(IndexedCookieCollection::class);
    });

    it('recommends SimpleCookieCollection one below threshold', function () {
        $type = CookieCollectionFactory::getRecommendedType(49);

        expect($type)->toBe(SimpleCookieCollection::class);
    });

    it('respects custom threshold in getRecommendedType()', function () {
        $type1 = CookieCollectionFactory::getRecommendedType(60, threshold: 100);
        $type2 = CookieCollectionFactory::getRecommendedType(60, threshold: 50);

        expect($type1)->toBe(SimpleCookieCollection::class);
        expect($type2)->toBe(IndexedCookieCollection::class);
    });

    // shouldUseIndexed() method tests
    it('returns false for count below threshold', function () {
        $result = CookieCollectionFactory::shouldUseIndexed(30);

        expect($result)->toBeFalse();
    });

    it('returns true for count at threshold', function () {
        $result = CookieCollectionFactory::shouldUseIndexed(50);

        expect($result)->toBeTrue();
    });

    it('returns true for count above threshold', function () {
        $result = CookieCollectionFactory::shouldUseIndexed(100);

        expect($result)->toBeTrue();
    });

    it('respects custom threshold in shouldUseIndexed()', function () {
        $result1 = CookieCollectionFactory::shouldUseIndexed(75, threshold: 100);
        $result2 = CookieCollectionFactory::shouldUseIndexed(75, threshold: 50);

        expect($result1)->toBeFalse();
        expect($result2)->toBeTrue();
    });

    // fromCookies() method tests
    it('creates SimpleCookieCollection from small cookie array', function () {
        $cookies = [
            new Cookie('cookie1', 'value1'),
            new Cookie('cookie2', 'value2'),
            new Cookie('cookie3', 'value3'),
        ];

        $collection = CookieCollectionFactory::fromCookies($cookies);

        expect($collection)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection->count())->toBe(3);
    });

    it('creates IndexedCookieCollection from large cookie array', function () {
        $cookies = [];
        for ($i = 0; $i < 60; $i++) {
            $cookies[] = new Cookie("cookie{$i}", "value{$i}");
        }

        $collection = CookieCollectionFactory::fromCookies($cookies);

        expect($collection)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($collection->count())->toBe(60);
    });

    it('creates empty collection from empty array', function () {
        $collection = CookieCollectionFactory::fromCookies([]);

        expect($collection)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection->isEmpty())->toBeTrue();
    });

    it('respects custom threshold in fromCookies()', function () {
        $cookies = [];
        for ($i = 0; $i < 30; $i++) {
            $cookies[] = new Cookie("cookie{$i}", "value{$i}");
        }

        $collection1 = CookieCollectionFactory::fromCookies($cookies, threshold: 50);
        $collection2 = CookieCollectionFactory::fromCookies($cookies, threshold: 20);

        expect($collection1)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($collection2)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('preserves all cookies when creating from array', function () {
        $cookie1 = new Cookie('session', 'abc123', null, 'example.com', '/');
        $cookie2 = new Cookie('token', 'xyz789', null, 'api.example.com', '/v1');
        $cookies = [$cookie1, $cookie2];

        $collection = CookieCollectionFactory::fromCookies($cookies);

        expect($collection->count())->toBe(2);
        expect($collection->get($cookie1->getIdentifier()))->toBe($cookie1);
        expect($collection->get($cookie2->getIdentifier()))->toBe($cookie2);
    });

    // createAdaptive() method tests
    it('creates AdaptiveCookieCollection with default threshold', function () {
        $collection = CookieCollectionFactory::createAdaptive();

        expect($collection)->toBeInstanceOf(AdaptiveCookieCollection::class);
    });

    it('creates AdaptiveCookieCollection with custom threshold', function () {
        $collection = CookieCollectionFactory::createAdaptive(threshold: 100);

        expect($collection)->toBeInstanceOf(AdaptiveCookieCollection::class);
    });

    // getDefaultThreshold() method tests
    it('returns correct default threshold', function () {
        $threshold = CookieCollectionFactory::getDefaultThreshold();

        expect($threshold)->toBe(50);
        expect($threshold)->toBe(CookieCollectionFactory::DEFAULT_THRESHOLD);
    });

    // migrate() method tests
    it('migrates from SimpleCookieCollection to IndexedCookieCollection', function () {
        $simple = new SimpleCookieCollection;
        $simple->add(new Cookie('cookie1', 'value1'));
        $simple->add(new Cookie('cookie2', 'value2'));

        $indexed = CookieCollectionFactory::migrate($simple, IndexedCookieCollection::class);

        expect($indexed)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($indexed->count())->toBe(2);
        expect($indexed->get('cookie1||/'))->not->toBeNull();
    });

    it('migrates from IndexedCookieCollection to SimpleCookieCollection', function () {
        $indexed = new IndexedCookieCollection;
        $indexed->add(new Cookie('cookie1', 'value1'));
        $indexed->add(new Cookie('cookie2', 'value2'));

        $simple = CookieCollectionFactory::migrate($indexed, SimpleCookieCollection::class);

        expect($simple)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($simple->count())->toBe(2);
    });

    it('returns same collection if already correct type', function () {
        $simple = new SimpleCookieCollection;
        $simple->add(new Cookie('cookie1', 'value1'));

        $result = CookieCollectionFactory::migrate($simple, SimpleCookieCollection::class);

        expect($result)->toBe($simple);
        expect($result->count())->toBe(1);
    });

    it('preserves all cookies during migration', function () {
        $simple = new SimpleCookieCollection;
        $cookie1 = new Cookie('session', 'abc123', null, 'example.com', '/');
        $cookie2 = new Cookie('token', 'xyz789', null, 'api.example.com', '/v1');
        $cookie3 = new Cookie('tracking', 'id999', time() + 3600, '.example.com', '/');

        $simple->add($cookie1);
        $simple->add($cookie2);
        $simple->add($cookie3);

        $indexed = CookieCollectionFactory::migrate($simple, IndexedCookieCollection::class);

        expect($indexed->count())->toBe(3);
        expect($indexed->get($cookie1->getIdentifier())->getValue())->toBe('abc123');
        expect($indexed->get($cookie2->getIdentifier())->getValue())->toBe('xyz789');
        expect($indexed->get($cookie3->getIdentifier())->getValue())->toBe('id999');
    });

    // Integration and edge case tests
    it('factory methods produce functional collections', function () {
        $simple = CookieCollectionFactory::createSimple();
        $indexed = CookieCollectionFactory::createIndexed();

        $cookie = new Cookie('test', 'value', null, 'example.com', '/');

        $simple->add($cookie);
        $indexed->add($cookie);

        $simpleMatches = $simple->findForUrl('https://example.com/', true);
        $indexedMatches = $indexed->findForUrl('https://example.com/', true);

        expect($simpleMatches)->toHaveCount(1);
        expect($indexedMatches)->toHaveCount(1);
    });

    it('respects threshold at boundary conditions', function () {
        // Test exactly at threshold boundary
        $at49 = CookieCollectionFactory::create(49);
        $at50 = CookieCollectionFactory::create(50);
        $at51 = CookieCollectionFactory::create(51);

        expect($at49)->toBeInstanceOf(SimpleCookieCollection::class);
        expect($at50)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($at51)->toBeInstanceOf(IndexedCookieCollection::class);
    });

    it('handles extreme threshold values', function () {
        $veryLowThreshold = CookieCollectionFactory::create(1, threshold: 1);
        $veryHighThreshold = CookieCollectionFactory::create(1000, threshold: 10000);

        expect($veryLowThreshold)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($veryHighThreshold)->toBeInstanceOf(SimpleCookieCollection::class);
    });

    it('migrates empty collections correctly', function () {
        $simple = new SimpleCookieCollection;

        $indexed = CookieCollectionFactory::migrate($simple, IndexedCookieCollection::class);

        expect($indexed)->toBeInstanceOf(IndexedCookieCollection::class);
        expect($indexed->isEmpty())->toBeTrue();
    });
});
