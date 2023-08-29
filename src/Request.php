<?php

namespace Farzai\Transport;

use Farzai\Transport\Traits\PsrRequestTrait;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;

class Request implements PsrRequestInterface
{
    use PsrRequestTrait;

    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1'
    ) {
        $this->request = new GuzzleRequest($method, $uri, $headers, $body, $version);
    }
}
