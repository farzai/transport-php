<?php

declare(strict_types=1);

namespace Farzai\Transport\Contracts;

use Farzai\Transport\Exceptions\SerializationException;

/**
 * Interface for serializing and deserializing data.
 *
 * This interface follows the Strategy Pattern, allowing different
 * serialization formats (JSON, XML, MessagePack, etc.) to be used
 * interchangeably throughout the application.
 */
interface SerializerInterface
{
    /**
     * Encode data to a string representation.
     *
     * @param  mixed  $data  The data to encode
     * @return string The encoded string
     *
     * @throws SerializationException When encoding fails
     */
    public function encode(mixed $data): string;

    /**
     * Decode a string into structured data.
     *
     * @param  string  $data  The string to decode
     * @param  string|null  $key  Optional key path to extract (e.g., "user.name")
     * @return mixed The decoded data
     *
     * @throws SerializationException When decoding fails
     */
    public function decode(string $data, ?string $key = null): mixed;

    /**
     * Safely decode a string, returning null on failure instead of throwing.
     *
     * @param  string  $data  The string to decode
     * @param  string|null  $key  Optional key path to extract
     * @return mixed The decoded data or null on failure
     */
    public function decodeOrNull(string $data, ?string $key = null): mixed;

    /**
     * Get the content type for this serializer.
     *
     * @return string The MIME type (e.g., "application/json")
     */
    public function getContentType(): string;
}
