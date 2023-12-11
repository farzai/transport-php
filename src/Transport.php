<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Exceptions\MaxRetriesExceededException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class Transport implements PsrClientInterface
{
    /**
     * The client instance.
     */
    private PsrClientInterface $client;

    /**
     * The logger instance.
     */
    private PsrLoggerInterface $logger;

    /**
     * The base URI.
     */
    private string $uri = '/';

    /**
     * The headers.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    private int $timeout = 30;

    private int $retries = 0;

    /**
     * Create a new client instance.
     */
    public function __construct(PsrClientInterface $client, PsrLoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Set the base URI.
     */
    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Get the URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Set the timeout.
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout < 0) {
            throw new \InvalidArgumentException('Timeout must be greater than or equal to 0.');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the retries.
     */
    public function setRetries(int $retries): self
    {
        if ($retries < 0) {
            throw new \InvalidArgumentException('Retries must be greater than or equal to 0.');
        }

        $this->retries = $retries;

        return $this;
    }

    /**
     * Get the retries.
     */
    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * Set the headers.
     *
     * @param  array<string, string>  $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Get the headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getLogger(): PsrLoggerInterface
    {
        return $this->logger;
    }

    public function sendRequest(PsrRequestInterface $request): PsrResponseInterface
    {
        $request = $this->setupRequest($request);

        $this->logger->info('[REQUEST] '.$request->getMethod().' '.$request->getUri());

        while ($this->retries >= 0) {
            try {
                return $this->client->sendRequest($request);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }

            $this->retries--;

            if ($this->retries >= 0) {
                $this->logger->info('[RETRY] '.$this->retries.' retries left.');
            }
        }

        $this->logger->error('[MAX RETRIES EXCEEDED]');

        throw ($e ?? new MaxRetriesExceededException('Max retries exceeded.'));
    }

    /**
     * Get the client instance.
     */
    public function getPsrClient(): PsrClientInterface
    {
        return $this->client;
    }

    /**
     * Setup the request.
     */
    public function setupRequest(PsrRequestInterface $request): PsrRequestInterface
    {
        $uri = $request->getUri();

        if (empty($uri->getHost())) {
            $request = $this->setupConnectionUri($request);
        }

        $request = $this->decorateRequest($request);

        return $request;
    }

    /**
     * Set the connection URI.
     */
    public function setupConnectionUri(PsrRequestInterface $request): PsrRequestInterface
    {
        $uri = new Uri($this->uri);
        $uri = $uri->withPath($uri->getPath().$request->getUri()->getPath());
        $uri = $uri->withQuery($request->getUri()->getQuery());

        return $request->withUri($uri);
    }

    /**
     * Decorate the request.
     */
    public function decorateRequest(PsrRequestInterface $request): PsrRequestInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
