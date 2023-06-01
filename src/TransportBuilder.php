<?php

declare(strict_types=1);

namespace Farzai\Transport;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TransportBuilder
{
    /**
     * The client instance.
     */
    private ?ClientInterface $client;

    /**
     * The logger instance.
     */
    private ?LoggerInterface $logger;

    /**
     * Create a new builder instance.
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * Set the client
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Build the transport.
     */
    public function build(): Transport
    {
        return new Transport(
            client: new LoggerAdapter(
                client: $this->client ?? new GuzzleClient(),
                logger: $this->logger ?? new NullLogger(),
            ),
        );
    }
}
