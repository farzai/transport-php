<?php

namespace Farzai\Transport\Traits;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

trait PsrRequestTrait
{
    protected PsrRequestInterface $request;

    public function getRequestTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget($requestTarget): PsrRequestInterface
    {
        $this->request = $this->request->withRequestTarget($requestTarget);

        return $this;
    }

    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    public function withMethod($method): PsrRequestInterface
    {
        $this->request = $this->request->withMethod($method);

        return $this;
    }

    public function getUri(): UriInterface
    {
        return $this->request->getUri();
    }

    public function withUri($uri, $preserveHost = false): PsrRequestInterface
    {
        $this->request = $this->request->withUri($uri, $preserveHost);

        return $this;
    }

    public function getProtocolVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    public function withProtocolVersion($version): PsrRequestInterface
    {
        $this->request = $this->request->withProtocolVersion($version);

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->request->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->request->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function withHeader($name, $value): PsrRequestInterface
    {
        $this->request = $this->request->withHeader($name, $value);

        return $this;
    }

    public function withAddedHeader($name, $value): PsrRequestInterface
    {
        $this->request = $this->request->withAddedHeader($name, $value);

        return $this;
    }

    public function withoutHeader($name): PsrRequestInterface
    {
        $this->request = $this->request->withoutHeader($name);

        return $this;
    }

    public function getBody(): StreamInterface
    {
        return $this->request->getBody();
    }

    public function withBody(StreamInterface $body): PsrRequestInterface
    {
        $this->request = $this->request->withBody($body);

        return $this;
    }
}
