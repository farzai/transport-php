<?php

declare(strict_types=1);

namespace Farzai\Transport;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class Transport implements PsrClientInterface
{
    const CLIENT_NAME = 'fz';

    const VERSION = '1.0.0';

    /**
     * The client instance.
     */
    private PsrClientInterface $client;

    /**
     * The base URI.
     */
    private string $uri = '/';

    /**
     * Create a new client instance.
     */
    public function __construct(PsrClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Send the request.
     */
    public function sendRequest(PsrRequestInterface $request): PsrResponseInterface
    {
        $uri = $request->getUri();

        if (empty($uri->getHost())) {
            $request = $request->withUri(new Uri($this->getUri().$uri->getPath()));
        }

        $request = $request->withHeader('User-Agent', self::CLIENT_NAME.'/'.self::VERSION);

        return $this->client->sendRequest($request);
    }

    /**
     * Get the URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get psr client.
     */
    public function getPsrClient(): PsrClientInterface
    {
        return $this->client;
    }
}
