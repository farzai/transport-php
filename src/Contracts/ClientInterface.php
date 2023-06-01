<?php

namespace Farzai\Transport\Contracts;

use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;

interface ClientInterface
{
    /**
     * Send the request.
     */
    public function sendRequest(PsrRequestInterface $request): ResponseInterface;

    /**
     * Get the PSR client.
     */
    public function getPsrClient(): PsrClientInterface;
}
