<?php

declare(strict_types=1);

namespace Farzai\Transport\Serialization;

use Farzai\Transport\Contracts\SerializerInterface;

/**
 * Factory for creating serializer instances.
 *
 * This class implements the Factory Pattern, providing a centralized
 * way to create appropriate serializers based on content type or
 * configuration requirements.
 *
 * Future extensions could include:
 * - XML serializer
 * - MessagePack serializer
 * - Protocol Buffers serializer
 * - Custom serializers registered at runtime
 */
final class SerializerFactory
{
    /**
     * Map of content types to serializer classes.
     *
     * @var array<string, class-string<SerializerInterface>>
     */
    private static array $contentTypeMap = [
        'application/json' => JsonSerializer::class,
        'text/json' => JsonSerializer::class,
        'application/javascript' => JsonSerializer::class,
        'application/x-javascript' => JsonSerializer::class,
    ];

    /**
     * Registry of custom serializers.
     *
     * @var array<string, SerializerInterface>
     */
    private static array $customSerializers = [];

    /**
     * Create a default JSON serializer with standard configuration.
     *
     * @return SerializerInterface The default serializer
     */
    public static function createDefault(): SerializerInterface
    {
        return new JsonSerializer(JsonConfig::default());
    }

    /**
     * Create a JSON serializer with strict configuration.
     *
     * Useful for validating untrusted input.
     *
     * @return SerializerInterface The strict serializer
     */
    public static function createStrict(): SerializerInterface
    {
        return new JsonSerializer(JsonConfig::strict());
    }

    /**
     * Create a JSON serializer with lenient configuration.
     *
     * More permissive settings for backwards compatibility.
     *
     * @return SerializerInterface The lenient serializer
     */
    public static function createLenient(): SerializerInterface
    {
        return new JsonSerializer(JsonConfig::lenient());
    }

    /**
     * Create a JSON serializer with pretty-print configuration.
     *
     * Useful for debugging and human-readable output.
     *
     * @return SerializerInterface The pretty-print serializer
     */
    public static function createPrettyPrint(): SerializerInterface
    {
        return new JsonSerializer(JsonConfig::prettyPrint());
    }

    /**
     * Create a serializer from a content type.
     *
     * This method examines the content type header and returns an
     * appropriate serializer. Currently only JSON is supported.
     *
     * @param  string  $contentType  The content type (e.g., "application/json")
     * @return SerializerInterface The appropriate serializer
     *
     * @throws \InvalidArgumentException When content type is not supported
     */
    public static function createFromContentType(string $contentType): SerializerInterface
    {
        // Normalize content type (remove charset, trim, lowercase)
        $normalizedType = strtolower(trim(explode(';', $contentType)[0]));

        // Check if a custom serializer is registered for this type
        if (isset(self::$customSerializers[$normalizedType])) {
            return self::$customSerializers[$normalizedType];
        }

        // Check if a built-in serializer exists for this type
        if (isset(self::$contentTypeMap[$normalizedType])) {
            $serializerClass = self::$contentTypeMap[$normalizedType];

            return new $serializerClass;
        }

        throw new \InvalidArgumentException(
            sprintf('Unsupported content type: %s', $contentType)
        );
    }

    /**
     * Create a JSON serializer with custom configuration.
     *
     * @param  JsonConfig  $config  The custom configuration
     * @return SerializerInterface The configured serializer
     */
    public static function createWithConfig(JsonConfig $config): SerializerInterface
    {
        return new JsonSerializer($config);
    }

    /**
     * Register a custom serializer for a content type.
     *
     * This allows extending the factory with custom serialization formats
     * without modifying the factory class itself (Open/Closed Principle).
     *
     * @param  string  $contentType  The content type to register
     * @param  SerializerInterface  $serializer  The serializer instance
     */
    public static function registerCustomSerializer(string $contentType, SerializerInterface $serializer): void
    {
        $normalizedType = strtolower(trim(explode(';', $contentType)[0]));
        self::$customSerializers[$normalizedType] = $serializer;
    }

    /**
     * Check if a content type is supported.
     *
     * @param  string  $contentType  The content type to check
     * @return bool True if supported, false otherwise
     */
    public static function supports(string $contentType): bool
    {
        $normalizedType = strtolower(trim(explode(';', $contentType)[0]));

        return isset(self::$customSerializers[$normalizedType])
            || isset(self::$contentTypeMap[$normalizedType]);
    }

    /**
     * Clear all custom serializers.
     *
     * Useful for testing.
     */
    public static function clearCustomSerializers(): void
    {
        self::$customSerializers = [];
    }
}
