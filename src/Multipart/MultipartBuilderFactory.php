<?php

declare(strict_types=1);

namespace Farzai\Transport\Multipart;

use Farzai\Transport\Factory\HttpFactory;

/**
 * Factory for creating the appropriate multipart builder based on content size.
 *
 * Design Pattern: Factory Pattern + Strategy Pattern
 * - Encapsulates builder selection logic
 * - Auto-selects optimal strategy based on file sizes
 * - Provides consistent interface regardless of strategy
 *
 * Selection Strategy:
 * - Total size < threshold: Standard builder (faster for small files)
 * - Total size >= threshold: Streaming builder (memory-efficient for large files)
 *
 * Default Threshold: 10 MB
 *
 * @example
 * ```php
 * // Auto-selection based on file sizes
 * $builder = MultipartBuilderFactory::create($httpFactory, $parts);
 *
 * // Force streaming builder
 * $builder = MultipartBuilderFactory::createStreaming($httpFactory);
 *
 * // Force standard builder
 * $builder = MultipartBuilderFactory::createStandard($httpFactory);
 *
 * // Custom threshold (50 MB)
 * $builder = MultipartBuilderFactory::create(
 *     $httpFactory,
 *     $parts,
 *     streamingThreshold: 50 * 1024 * 1024
 * );
 * ```
 */
final class MultipartBuilderFactory
{
    /**
     * Default threshold for switching to streaming (10 MB).
     */
    public const DEFAULT_STREAMING_THRESHOLD = 10 * 1024 * 1024;

    /**
     * Create a multipart builder, auto-selecting based on content size.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     * @param  array<array<string, mixed>>|null  $parts  Optional parts array to analyze
     * @param  int  $streamingThreshold  Size threshold in bytes (default 10 MB)
     * @param  string|null  $boundary  Optional custom boundary
     */
    public static function create(
        ?HttpFactory $httpFactory = null,
        ?array $parts = null,
        int $streamingThreshold = self::DEFAULT_STREAMING_THRESHOLD,
        ?string $boundary = null
    ): MultipartStreamBuilder|StreamingMultipartBuilder {
        $httpFactory = $httpFactory ?? HttpFactory::getInstance();

        // If no parts provided, default to standard builder
        if ($parts === null || empty($parts)) {
            return new MultipartStreamBuilder($boundary, $httpFactory);
        }

        // Calculate total size of all parts
        $totalSize = self::calculateTotalSize($parts);

        // If size cannot be determined, use streaming to be safe
        if ($totalSize === null) {
            return new StreamingMultipartBuilder($httpFactory, $boundary);
        }

        // Select builder based on total size
        if ($totalSize >= $streamingThreshold) {
            return new StreamingMultipartBuilder($httpFactory, $boundary);
        }

        return new MultipartStreamBuilder($boundary, $httpFactory);
    }

    /**
     * Create a standard (non-streaming) multipart builder.
     *
     * Use for small files where speed is more important than memory efficiency.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     * @param  string|null  $boundary  Optional custom boundary
     */
    public static function createStandard(
        ?HttpFactory $httpFactory = null,
        ?string $boundary = null
    ): MultipartStreamBuilder {
        $httpFactory = $httpFactory ?? HttpFactory::getInstance();

        return new MultipartStreamBuilder($boundary, $httpFactory);
    }

    /**
     * Create a streaming multipart builder.
     *
     * Use for large files or when memory efficiency is critical.
     *
     * @param  HttpFactory|null  $httpFactory  Optional HTTP factory
     * @param  string|null  $boundary  Optional custom boundary
     * @param  int  $chunkSize  Chunk size in bytes (default 8192)
     */
    public static function createStreaming(
        ?HttpFactory $httpFactory = null,
        ?string $boundary = null,
        int $chunkSize = 8192
    ): StreamingMultipartBuilder {
        $httpFactory = $httpFactory ?? HttpFactory::getInstance();

        return new StreamingMultipartBuilder($httpFactory, $boundary, $chunkSize);
    }

    /**
     * Get the recommended builder type for a given size.
     *
     * @param  int  $totalBytes  Total size in bytes
     * @param  int  $threshold  Streaming threshold in bytes
     * @return class-string<MultipartStreamBuilder|StreamingMultipartBuilder>
     */
    public static function getRecommendedBuilder(
        int $totalBytes,
        int $threshold = self::DEFAULT_STREAMING_THRESHOLD
    ): string {
        if ($totalBytes >= $threshold) {
            return StreamingMultipartBuilder::class;
        }

        return MultipartStreamBuilder::class;
    }

    /**
     * Check if streaming is recommended for given parts.
     *
     * @param  array<array<string, mixed>>  $parts  The parts to analyze
     * @param  int  $threshold  Streaming threshold in bytes
     * @return bool True if streaming recommended, false otherwise
     */
    public static function shouldUseStreaming(
        array $parts,
        int $threshold = self::DEFAULT_STREAMING_THRESHOLD
    ): bool {
        $totalSize = self::calculateTotalSize($parts);

        // If size unknown, recommend streaming to be safe
        if ($totalSize === null) {
            return true;
        }

        return $totalSize >= $threshold;
    }

    /**
     * Calculate total size of all parts.
     *
     * Returns null if any part's size cannot be determined.
     *
     * @param  array<array<string, mixed>>  $parts
     * @return int|null Total size in bytes, or null if unknown
     */
    private static function calculateTotalSize(array $parts): ?int
    {
        $totalSize = 0;

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $contents = $part['contents'] ?? null;
            $filename = $part['filename'] ?? null;

            // Skip if no contents
            if ($contents === null) {
                continue;
            }

            // File path
            if (is_string($contents) && $filename !== null && is_file($contents)) {
                $size = filesize($contents);
                if ($size === false) {
                    return null; // Cannot determine size
                }
                $totalSize += $size;

                continue;
            }

            // String contents
            if (is_string($contents)) {
                $totalSize += strlen($contents);

                continue;
            }

            // Stream contents
            if ($contents instanceof \Psr\Http\Message\StreamInterface) {
                $size = $contents->getSize();
                if ($size === null) {
                    return null; // Cannot determine size
                }
                $totalSize += $size;

                continue;
            }

            // Unknown content type
            return null;
        }

        return $totalSize;
    }

    /**
     * Format size in human-readable format.
     *
     * @param  int  $bytes  Size in bytes
     * @return string Formatted size (e.g., "10.5 MB")
     */
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);

        return sprintf('%.1f %s', $size, $units[$power]);
    }

    /**
     * Get the default streaming threshold.
     *
     * @return int Threshold in bytes (10 MB)
     */
    public static function getDefaultThreshold(): int
    {
        return self::DEFAULT_STREAMING_THRESHOLD;
    }

    /**
     * Get the default streaming threshold in human-readable format.
     *
     * @return string Formatted threshold (e.g., "10.0 MB")
     */
    public static function getDefaultThresholdFormatted(): string
    {
        return self::formatSize(self::DEFAULT_STREAMING_THRESHOLD);
    }
}
