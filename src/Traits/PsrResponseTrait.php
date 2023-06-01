<?php

namespace Farzai\Transport\Traits;

trait PsrResponseTrait
{
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        return $this->response->getBody();
    }

    public function withProtocolVersion($version): static
    {
        return new static($this->request, $this->response->withProtocolVersion($version));
    }

    public function withHeader($name, $value): static
    {
        return new static($this->request, $this->response->withHeader($name, $value));
    }

    public function withAddedHeader($name, $value): static
    {
        return new static($this->request, $this->response->withAddedHeader($name, $value));
    }

    public function withoutHeader($name): static
    {
        return new static($this->request, $this->response->withoutHeader($name));
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        return new static($this->request, $this->response->withBody($body));
    }

    public function withStatus($code, $reasonPhrase = ''): static
    {
        return new static($this->request, $this->response->withStatus($code, $reasonPhrase));
    }
}
