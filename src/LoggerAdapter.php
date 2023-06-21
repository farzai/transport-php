<?php

namespace Farzai\Transport;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class LoggerAdapter implements ClientInterface
{
    /**
     * @var \Psr\Http\Client\ClientInterface
     */
    protected $client;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Create a new client instance.
     */
    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->logger->debug(sprintf(
            'REQUEST: [%s] - %s: %s',
            date('Y-m-d H:i:s.u'),
            $request->getMethod(),
            $request->getUri()
        ));

        $response = $this->client->sendRequest($request);

        $this->logger->debug(sprintf(
            'RESPONSE: [%s] - %s: %s - %s',
            date('Y-m-d H:i:s.u'),
            $request->getMethod(),
            $request->getUri(),
            $response->getStatusCode()
        ));

        return $response;
    }
}
