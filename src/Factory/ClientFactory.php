<?php

declare(strict_types=1);

namespace Farzai\Transport\Factory;

use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP Client Factory with auto-detection capabilities.
 *
 * This factory automatically detects and instantiates available PSR-18 HTTP clients.
 * It provides a flexible way to work with different HTTP client implementations
 * without hard-coding dependencies.
 *
 * Design Pattern: Factory Pattern + Strategy Pattern
 * - Encapsulates client creation logic
 * - Auto-detects available PSR-18 implementations
 * - Allows custom client injection
 * - Supports logging for debugging
 *
 * Discovery Order:
 * 1. Symfony HTTP Client (if installed)
 * 2. Guzzle HTTP Client (if installed)
 * 3. Any other PSR-18 compliant client
 *
 * @example
 * ```php
 * // Auto-detect any available PSR-18 client
 * $client = ClientFactory::create();
 *
 * // With logging to see which client was detected
 * $client = ClientFactory::create($logger);
 *
 * // Explicitly specify a client type
 * $client = ClientFactory::createGuzzle();
 * $client = ClientFactory::createSymfony();
 * ```
 */
final class ClientFactory
{
    /**
     * Create a PSR-18 HTTP client with auto-detection.
     *
     * This method attempts to auto-detect an available PSR-18 HTTP client
     * implementation. It logs which client was detected if a logger is provided.
     *
     * @param  LoggerInterface|null  $logger  Optional logger for debugging
     * @return ClientInterface The detected HTTP client
     *
     * @throws \RuntimeException If no PSR-18 client implementation is available
     *
     * @example
     * ```php
     * $client = ClientFactory::create();
     * // or with logging
     * $client = ClientFactory::create($logger);
     * ```
     */
    public static function create(?LoggerInterface $logger = null): ClientInterface
    {
        $logger = $logger ?? new NullLogger;

        // Try Symfony HTTP Client first (modern, async support, HTTP/2)
        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $logger->debug('ClientFactory: Using Symfony HTTP Client');

            return new \Symfony\Component\HttpClient\Psr18Client;
        }

        // Try Guzzle HTTP Client (popular, widely used)
        if (class_exists('GuzzleHttp\Client')) {
            $logger->debug('ClientFactory: Using Guzzle HTTP Client');

            return new \GuzzleHttp\Client;
        }

        // Fallback to PSR-18 discovery (will find any installed PSR-18 client)
        try {
            $client = Psr18ClientDiscovery::find();
            $logger->debug('ClientFactory: Using auto-detected PSR-18 client', [
                'client' => get_class($client),
            ]);

            return $client;
        } catch (\Throwable $e) {
            $logger->error('ClientFactory: No PSR-18 HTTP client found', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'No PSR-18 HTTP client implementation found. '.
                'Please install one: composer require guzzlehttp/guzzle or composer require symfony/http-client',
                0,
                $e
            );
        }
    }

    /**
     * Create a Guzzle HTTP client specifically.
     *
     * @param  array<string, mixed>  $config  Guzzle configuration options
     * @return ClientInterface Guzzle HTTP client
     *
     * @throws \RuntimeException If Guzzle is not installed
     *
     * @example
     * ```php
     * $client = ClientFactory::createGuzzle([
     *     'timeout' => 30,
     *     'verify' => true,
     * ]);
     * ```
     */
    public static function createGuzzle(array $config = []): ClientInterface
    {
        if (! class_exists('GuzzleHttp\Client')) {
            throw new \RuntimeException(
                'Guzzle HTTP client is not installed. Install it with: composer require guzzlehttp/guzzle'
            );
        }

        return new \GuzzleHttp\Client($config);
    }

    /**
     * Create a Symfony HTTP client specifically.
     *
     * @param  array<string, mixed>  $options  Symfony HTTP client options
     * @return ClientInterface Symfony HTTP client
     *
     * @throws \RuntimeException If Symfony HTTP Client is not installed
     *
     * @example
     * ```php
     * $client = ClientFactory::createSymfony([
     *     'timeout' => 30,
     *     'max_redirects' => 5,
     * ]);
     * ```
     */
    public static function createSymfony(array $options = []): ClientInterface
    {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            throw new \RuntimeException(
                'Symfony HTTP Client is not installed. Install it with: composer require symfony/http-client'
            );
        }

        // If options are provided, create a configured HttpClient first
        if (! empty($options)) {
            $httpClient = \Symfony\Component\HttpClient\HttpClient::create($options);

            return new \Symfony\Component\HttpClient\Psr18Client($httpClient);
        }

        return new \Symfony\Component\HttpClient\Psr18Client;
    }

    /**
     * Check if a specific client type is available.
     *
     * @param  string  $clientType  Client type: 'guzzle', 'symfony', or FQCN
     * @return bool True if the client is available
     *
     * @example
     * ```php
     * if (ClientFactory::isAvailable('guzzle')) {
     *     $client = ClientFactory::createGuzzle();
     * }
     * ```
     */
    public static function isAvailable(string $clientType): bool
    {
        return match (strtolower($clientType)) {
            'guzzle' => class_exists('GuzzleHttp\Client'),
            'symfony' => class_exists('Symfony\Component\HttpClient\Psr18Client'),
            default => class_exists($clientType),
        };
    }

    /**
     * Get the name of the auto-detected client.
     *
     * This is useful for debugging and logging purposes.
     *
     * @return string The class name of the detected client
     *
     * @throws \RuntimeException If no client is available
     *
     * @example
     * ```php
     * echo "Using client: " . ClientFactory::getDetectedClientName();
     * // Output: "Using client: GuzzleHttp\Client"
     * ```
     */
    public static function getDetectedClientName(): string
    {
        try {
            $client = self::create();

            return get_class($client);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No HTTP client could be detected', 0, $e);
        }
    }
}
