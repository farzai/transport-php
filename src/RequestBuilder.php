<?php

declare(strict_types=1);

namespace Farzai\Transport;

use Farzai\Transport\Contracts\ResponseInterface;
use Farzai\Transport\Contracts\SerializerInterface;
use Farzai\Transport\Factory\HttpFactory;
use Farzai\Transport\Serialization\SerializerFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class RequestBuilder
{
    private string $method = 'GET';

    private UriInterface $uri;

    /**
     * @var array<string, string|array<string>>
     */
    private array $headers = [];

    private StreamInterface|string|null $body = null;

    private ?Transport $transport = null;

    private SerializerInterface $serializer;

    private HttpFactory $httpFactory;

    public function __construct(
        ?Transport $transport = null,
        ?SerializerInterface $serializer = null,
        ?HttpFactory $httpFactory = null
    ) {
        $this->transport = $transport;
        $this->httpFactory = $httpFactory ?? HttpFactory::getInstance();
        $this->uri = $this->httpFactory->createUri();
        $this->serializer = $serializer ?? SerializerFactory::createDefault();
    }

    /**
     * Set the HTTP method.
     */
    public function method(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    /**
     * Set the URI.
     */
    public function uri(UriInterface|string $uri): self
    {
        $clone = clone $this;
        $clone->uri = is_string($uri) ? $this->httpFactory->createUri($uri) : $uri;

        return $clone;
    }

    /**
     * Add a header.
     *
     * @param  string|array<string>  $value
     */
    public function withHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * Add multiple headers.
     *
     * @param  array<string, string|array<string>>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    /**
     * Set the request body.
     */
    public function withBody(StreamInterface|string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * Set JSON body.
     *
     * Uses the injected serializer for encoding with proper error handling.
     *
     * @param  mixed  $data  The data to encode as JSON
     *
     * @throws \Farzai\Transport\Exceptions\JsonEncodeException When encoding fails
     */
    public function withJson(mixed $data): self
    {
        $clone = clone $this;
        $clone->body = $this->serializer->encode($data);
        $clone->headers['Content-Type'] = $this->serializer->getContentType();

        return $clone;
    }

    /**
     * Set form data body.
     *
     * @param  array<string, mixed>  $data
     */
    public function withForm(array $data): self
    {
        $clone = clone $this;
        $clone->body = http_build_query($data);
        $clone->headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return $clone;
    }

    /**
     * Add query parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public function withQuery(array $params): self
    {
        $clone = clone $this;

        // Parse existing query
        $existing = [];
        parse_str($clone->uri->getQuery(), $existing);

        // Merge with new params
        $merged = array_merge($existing, $params);

        // Update URI with new query
        $clone->uri = $clone->uri->withQuery(http_build_query($merged));

        return $clone;
    }

    /**
     * Set basic authentication.
     */
    public function withBasicAuth(string $username, string $password): self
    {
        $credentials = base64_encode($username.':'.$password);

        return $this->withHeader('Authorization', 'Basic '.$credentials);
    }

    /**
     * Set bearer token authentication.
     */
    public function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    /**
     * Build the PSR-7 request.
     */
    public function build(): RequestInterface
    {
        $request = $this->httpFactory->createRequest($this->method, $this->uri);

        // Add headers
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Add body if present
        if ($this->body !== null) {
            if (is_string($this->body)) {
                $stream = $this->httpFactory->createStream($this->body);
                $request = $request->withBody($stream);
            } else {
                $request = $request->withBody($this->body);
            }
        }

        return $request;
    }

    /**
     * Send the request using the transport.
     */
    public function send(): ResponseInterface
    {
        if ($this->transport === null) {
            throw new \RuntimeException('No transport instance available. Use Transport::request() or provide transport in constructor.');
        }

        return $this->transport->sendRequest($this->build());
    }

    // Convenience methods for HTTP verbs

    /**
     * Create a GET request.
     */
    public static function get(string|UriInterface $uri): self
    {
        return (new self)->method('GET')->uri($uri);
    }

    /**
     * Create a POST request.
     */
    public static function post(string|UriInterface $uri): self
    {
        return (new self)->method('POST')->uri($uri);
    }

    /**
     * Create a PUT request.
     */
    public static function put(string|UriInterface $uri): self
    {
        return (new self)->method('PUT')->uri($uri);
    }

    /**
     * Create a PATCH request.
     */
    public static function patch(string|UriInterface $uri): self
    {
        return (new self)->method('PATCH')->uri($uri);
    }

    /**
     * Create a DELETE request.
     */
    public static function delete(string|UriInterface $uri): self
    {
        return (new self)->method('DELETE')->uri($uri);
    }

    /**
     * Create a HEAD request.
     */
    public static function head(string|UriInterface $uri): self
    {
        return (new self)->method('HEAD')->uri($uri);
    }

    /**
     * Create an OPTIONS request.
     */
    public static function options(string|UriInterface $uri): self
    {
        return (new self)->method('OPTIONS')->uri($uri);
    }
}
