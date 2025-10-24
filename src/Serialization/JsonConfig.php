<?php

declare(strict_types=1);

namespace Farzai\Transport\Serialization;

/**
 * Immutable configuration for JSON serialization.
 *
 * This class follows the Configuration Object pattern and provides
 * sensible defaults optimized for performance and safety.
 */
final class JsonConfig
{
    /**
     * Create a new JSON configuration.
     *
     * @param  int  $maxDepth  Maximum nesting depth (default: 512, protects against stack overflow)
     * @param  int  $encodeFlags  Flags for json_encode (default: optimized for performance and correctness)
     * @param  int  $decodeFlags  Flags for json_decode (default: includes BIGINT_AS_STRING to prevent overflow)
     * @param  bool  $associative  Decode as associative array (true) or object (false)
     */
    public function __construct(
        public readonly int $maxDepth = 512,
        public readonly int $encodeFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        public readonly int $decodeFlags = JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING,
        public readonly bool $associative = true
    ) {
        $this->validate();
    }

    /**
     * Create a default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create a strict configuration for validation.
     *
     * Uses stricter settings suitable for validating untrusted input.
     */
    public static function strict(): self
    {
        return new self(
            maxDepth: 128, // Lower depth limit
            encodeFlags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            decodeFlags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING,
            associative: true
        );
    }

    /**
     * Create a lenient configuration.
     *
     * More permissive settings for backwards compatibility.
     */
    public static function lenient(): self
    {
        return new self(
            maxDepth: 1024,
            encodeFlags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            decodeFlags: JSON_BIGINT_AS_STRING | JSON_INVALID_UTF8_SUBSTITUTE,
            associative: true
        );
    }

    /**
     * Create a pretty-print configuration for debugging.
     */
    public static function prettyPrint(): self
    {
        return new self(
            encodeFlags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Create a new configuration with a different max depth.
     */
    public function withMaxDepth(int $maxDepth): self
    {
        return new self(
            maxDepth: $maxDepth,
            encodeFlags: $this->encodeFlags,
            decodeFlags: $this->decodeFlags,
            associative: $this->associative
        );
    }

    /**
     * Create a new configuration with different encode flags.
     */
    public function withEncodeFlags(int $flags): self
    {
        return new self(
            maxDepth: $this->maxDepth,
            encodeFlags: $flags,
            decodeFlags: $this->decodeFlags,
            associative: $this->associative
        );
    }

    /**
     * Create a new configuration with different decode flags.
     */
    public function withDecodeFlags(int $flags): self
    {
        return new self(
            maxDepth: $this->maxDepth,
            encodeFlags: $this->encodeFlags,
            decodeFlags: $flags,
            associative: $this->associative
        );
    }

    /**
     * Create a new configuration with different associative setting.
     */
    public function withAssociative(bool $associative): self
    {
        return new self(
            maxDepth: $this->maxDepth,
            encodeFlags: $this->encodeFlags,
            decodeFlags: $this->decodeFlags,
            associative: $associative
        );
    }

    /**
     * Validate the configuration.
     *
     * @throws \InvalidArgumentException When configuration is invalid
     */
    private function validate(): void
    {
        if ($this->maxDepth < 1) {
            throw new \InvalidArgumentException('Max depth must be at least 1.');
        }

        if ($this->maxDepth > 2147483647) {
            throw new \InvalidArgumentException('Max depth exceeds maximum allowed value.');
        }
    }
}
