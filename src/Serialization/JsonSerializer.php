<?php

declare(strict_types=1);

namespace Farzai\Transport\Serialization;

use Farzai\Support\Arr;
use Farzai\Transport\Contracts\SerializerInterface;
use Farzai\Transport\Exceptions\JsonEncodeException;
use Farzai\Transport\Exceptions\JsonParseException;

/**
 * High-performance JSON serializer implementation.
 *
 * This class implements the Strategy Pattern, providing optimized JSON
 * encoding and decoding with configurable depth limits, error handling,
 * and protection against common JSON pitfalls.
 *
 * Features:
 * - Modern PHP 8.1+ error handling with JSON_THROW_ON_ERROR
 * - Configurable nesting depth to prevent stack overflow
 * - Big integer handling to prevent overflow (JSON_BIGINT_AS_STRING)
 * - Performance-optimized encode flags
 * - Dot notation support for accessing nested values
 */
final class JsonSerializer implements SerializerInterface
{
    /**
     * Create a new JSON serializer.
     *
     * @param  JsonConfig  $config  The configuration for JSON operations
     */
    public function __construct(
        private readonly JsonConfig $config = new JsonConfig
    ) {
        //
    }

    /**
     * Encode data to JSON string.
     *
     * Uses modern PHP error handling and performance-optimized flags.
     *
     * @param  mixed  $data  The data to encode
     * @return string The JSON string
     *
     * @throws JsonEncodeException When encoding fails
     */
    public function encode(mixed $data): string
    {
        try {
            $result = json_encode(
                value: $data,
                flags: $this->config->encodeFlags,
                depth: max(1, $this->config->maxDepth)
            );

            // With JSON_THROW_ON_ERROR, this should never be false, but satisfy PHPStan
            if ($result === false) {
                throw new \JsonException('JSON encoding failed');
            }

            return $result;
        } catch (\JsonException $e) {
            throw JsonEncodeException::fromJsonException($e, $data, $this->config->maxDepth);
        }
    }

    /**
     * Decode JSON string to structured data.
     *
     * Uses modern PHP error handling and protects against integer overflow
     * by using JSON_BIGINT_AS_STRING.
     *
     * @param  string  $data  The JSON string to decode
     * @param  string|null  $key  Optional dot-notation key path (e.g., "user.name")
     * @return mixed The decoded data
     *
     * @throws JsonParseException When decoding fails
     */
    public function decode(string $data, ?string $key = null): mixed
    {
        // Handle empty string
        if ($data === '') {
            return null;
        }

        try {
            $decoded = json_decode(
                json: $data,
                associative: $this->config->associative,
                depth: max(1, $this->config->maxDepth),
                flags: $this->config->decodeFlags
            );

            // If no key path specified, return the full decoded data
            if ($key === null) {
                return $decoded;
            }

            // Extract nested value using dot notation
            return $this->extractValue($decoded, $key);
        } catch (\JsonException $e) {
            throw JsonParseException::fromJsonException($e, $data, $this->config->maxDepth);
        }
    }

    /**
     * Safely decode JSON, returning null on failure.
     *
     * This method catches all JSON parsing exceptions and returns null
     * instead, making it useful for optional or best-effort parsing.
     *
     * @param  string  $data  The JSON string to decode
     * @param  string|null  $key  Optional dot-notation key path
     * @return mixed The decoded data or null on failure
     */
    public function decodeOrNull(string $data, ?string $key = null): mixed
    {
        try {
            return $this->decode($data, $key);
        } catch (JsonParseException) {
            return null;
        }
    }

    /**
     * Get the content type for JSON.
     *
     * @return string The MIME type
     */
    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Get the configuration.
     *
     * @return JsonConfig The current configuration
     */
    public function getConfig(): JsonConfig
    {
        return $this->config;
    }

    /**
     * Extract a value from decoded data using dot notation.
     *
     * @param  mixed  $data  The decoded data
     * @param  string  $key  The dot-notation key path
     * @return mixed The extracted value
     */
    private function extractValue(mixed $data, string $key): mixed
    {
        // If data is an array, use the Arr helper from farzai/support
        if (is_array($data)) {
            return Arr::get($data, $key);
        }

        // If data is an object, convert to array for extraction
        if (is_object($data)) {
            $json = json_encode($data, JSON_THROW_ON_ERROR);

            return Arr::get(json_decode($json, true), $key);
        }

        // For scalar values, only return if key is empty
        return $key === '' ? $data : null;
    }
}
