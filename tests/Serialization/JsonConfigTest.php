<?php

declare(strict_types=1);

use Farzai\Transport\Serialization\JsonConfig;

describe('JsonConfig', function () {
    it('creates default configuration', function () {
        $config = JsonConfig::default();

        expect($config->maxDepth)->toBe(512)
            ->and($config->encodeFlags)->toBe(JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->and($config->decodeFlags)->toBe(JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)
            ->and($config->associative)->toBeTrue();
    });

    it('creates strict configuration', function () {
        $config = JsonConfig::strict();

        expect($config->maxDepth)->toBe(128)
            ->and($config->encodeFlags & JSON_THROW_ON_ERROR)->toBeGreaterThan(0)
            ->and($config->decodeFlags & JSON_THROW_ON_ERROR)->toBeGreaterThan(0);
    });

    it('creates lenient configuration', function () {
        $config = JsonConfig::lenient();

        expect($config->maxDepth)->toBe(1024)
            ->and($config->encodeFlags & JSON_INVALID_UTF8_SUBSTITUTE)->toBeGreaterThan(0);
    });

    it('creates pretty-print configuration', function () {
        $config = JsonConfig::prettyPrint();

        expect($config->encodeFlags & JSON_PRETTY_PRINT)->toBeGreaterThan(0);
    });

    it('is immutable', function () {
        $config1 = JsonConfig::default();
        $config2 = $config1->withMaxDepth(256);

        expect($config1)->not->toBe($config2)
            ->and($config1->maxDepth)->toBe(512)
            ->and($config2->maxDepth)->toBe(256);
    });

    it('can modify max depth', function () {
        $config = JsonConfig::default()->withMaxDepth(256);

        expect($config->maxDepth)->toBe(256);
    });

    it('can modify encode flags', function () {
        $config = JsonConfig::default()->withEncodeFlags(JSON_PRETTY_PRINT);

        expect($config->encodeFlags)->toBe(JSON_PRETTY_PRINT);
    });

    it('can modify decode flags', function () {
        $config = JsonConfig::default()->withDecodeFlags(JSON_BIGINT_AS_STRING);

        expect($config->decodeFlags)->toBe(JSON_BIGINT_AS_STRING);
    });

    it('can modify associative setting', function () {
        $config = JsonConfig::default()->withAssociative(false);

        expect($config->associative)->toBeFalse();
    });

    it('throws exception for invalid max depth', function () {
        new JsonConfig(maxDepth: 0);
    })->throws(\InvalidArgumentException::class, 'Max depth must be at least 1');

    it('throws exception for negative max depth', function () {
        new JsonConfig(maxDepth: -1);
    })->throws(\InvalidArgumentException::class);

    it('throws exception when max depth exceeds maximum value', function () {
        new JsonConfig(maxDepth: 2147483648); // PHP_INT_MAX + 1 on 32-bit or exceeds validation
    })->throws(\InvalidArgumentException::class, 'Max depth exceeds maximum allowed value');

    it('allows chaining configuration methods', function () {
        $config = JsonConfig::default()
            ->withMaxDepth(256)
            ->withAssociative(false)
            ->withEncodeFlags(JSON_PRETTY_PRINT);

        expect($config->maxDepth)->toBe(256)
            ->and($config->associative)->toBeFalse()
            ->and($config->encodeFlags)->toBe(JSON_PRETTY_PRINT);
    });
});
