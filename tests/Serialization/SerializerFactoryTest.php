<?php

declare(strict_types=1);

use Farzai\Transport\Contracts\SerializerInterface;
use Farzai\Transport\Serialization\JsonConfig;
use Farzai\Transport\Serialization\JsonSerializer;
use Farzai\Transport\Serialization\SerializerFactory;

describe('SerializerFactory', function () {
    beforeEach(function () {
        // Clean up custom serializers before each test
        SerializerFactory::clearCustomSerializers();
    });

    it('creates default JSON serializer', function () {
        $serializer = SerializerFactory::createDefault();

        expect($serializer)->toBeInstanceOf(JsonSerializer::class)
            ->and($serializer)->toBeInstanceOf(SerializerInterface::class);
    });

    it('creates strict JSON serializer', function () {
        $serializer = SerializerFactory::createStrict();

        expect($serializer)->toBeInstanceOf(JsonSerializer::class)
            ->and($serializer->getConfig()->maxDepth)->toBe(128);
    });

    it('creates lenient JSON serializer', function () {
        $serializer = SerializerFactory::createLenient();

        expect($serializer)->toBeInstanceOf(JsonSerializer::class)
            ->and($serializer->getConfig()->maxDepth)->toBe(1024);
    });

    it('creates pretty-print JSON serializer', function () {
        $serializer = SerializerFactory::createPrettyPrint();

        expect($serializer)->toBeInstanceOf(JsonSerializer::class)
            ->and($serializer->getConfig()->encodeFlags & JSON_PRETTY_PRINT)->toBeGreaterThan(0);
    });

    it('creates serializer with custom config', function () {
        $config = JsonConfig::default()->withMaxDepth(256);
        $serializer = SerializerFactory::createWithConfig($config);

        expect($serializer)->toBeInstanceOf(JsonSerializer::class)
            ->and($serializer->getConfig())->toBe($config);
    });

    it('creates serializer from application/json content type', function () {
        $serializer = SerializerFactory::createFromContentType('application/json');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });

    it('creates serializer from text/json content type', function () {
        $serializer = SerializerFactory::createFromContentType('text/json');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });

    it('creates serializer from content type with charset', function () {
        $serializer = SerializerFactory::createFromContentType('application/json; charset=utf-8');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });

    it('normalizes content type case', function () {
        $serializer = SerializerFactory::createFromContentType('APPLICATION/JSON');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });

    it('throws exception for unsupported content type', function () {
        SerializerFactory::createFromContentType('application/xml');
    })->throws(\InvalidArgumentException::class, 'Unsupported content type');

    it('can register custom serializer', function () {
        $customSerializer = Mockery::mock(SerializerInterface::class);
        SerializerFactory::registerCustomSerializer('application/custom', $customSerializer);

        $result = SerializerFactory::createFromContentType('application/custom');

        expect($result)->toBe($customSerializer);
    });

    it('custom serializer overrides built-in', function () {
        $customSerializer = Mockery::mock(SerializerInterface::class);
        SerializerFactory::registerCustomSerializer('application/json', $customSerializer);

        $result = SerializerFactory::createFromContentType('application/json');

        expect($result)->toBe($customSerializer);
    });

    it('checks if content type is supported', function () {
        expect(SerializerFactory::supports('application/json'))->toBeTrue()
            ->and(SerializerFactory::supports('text/json'))->toBeTrue()
            ->and(SerializerFactory::supports('application/xml'))->toBeFalse();
    });

    it('supports custom content types', function () {
        $customSerializer = Mockery::mock(SerializerInterface::class);
        SerializerFactory::registerCustomSerializer('application/custom', $customSerializer);

        expect(SerializerFactory::supports('application/custom'))->toBeTrue();
    });

    it('supports content type with charset', function () {
        expect(SerializerFactory::supports('application/json; charset=utf-8'))->toBeTrue();
    });

    it('can clear custom serializers', function () {
        $customSerializer = Mockery::mock(SerializerInterface::class);
        SerializerFactory::registerCustomSerializer('application/custom', $customSerializer);

        expect(SerializerFactory::supports('application/custom'))->toBeTrue();

        SerializerFactory::clearCustomSerializers();

        expect(SerializerFactory::supports('application/custom'))->toBeFalse();
    });

    it('supports application/javascript content type', function () {
        $serializer = SerializerFactory::createFromContentType('application/javascript');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });

    it('supports application/x-javascript content type', function () {
        $serializer = SerializerFactory::createFromContentType('application/x-javascript');

        expect($serializer)->toBeInstanceOf(JsonSerializer::class);
    });
});

afterEach(function () {
    Mockery::close();
    SerializerFactory::clearCustomSerializers();
});
