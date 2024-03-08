<?php

use Farzai\Transport\Request;
use Farzai\Transport\Transport;
use Farzai\Transport\TransportBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

it('can build transport with default values from builder', function () {
    $transport = TransportBuilder::make()->build();

    expect($transport)->toBeInstanceOf(Transport::class);
    expect($transport->getPsrClient())->toBeInstanceOf(GuzzleClient::class);
    expect($transport->getLogger())->toBeInstanceOf(NullLogger::class);

    expect($transport->getUri())->toBe('/');
});

it('can build transport with builder', function () {
    $builder = TransportBuilder::make()
        ->setClient(new GuzzleClient())
        ->setLogger(new NullLogger());

    expect($builder->getClient())->toBeInstanceOf(GuzzleClient::class);
    expect($builder->getLogger())->toBeInstanceOf(NullLogger::class);

    $transport = $builder->build();

    expect($transport)->toBeInstanceOf(Transport::class);
    expect($transport->getPsrClient())->toBeInstanceOf(GuzzleClient::class);
    expect($transport->getLogger())->toBeInstanceOf(NullLogger::class);

    expect($transport->getUri())->toBe('/');
});

it('should throw error if set timeout less than 0', function () {
    $transport = TransportBuilder::make()
        ->setClient(new GuzzleClient())
        ->setLogger(new NullLogger())
        ->build();

    $transport->setTimeout(-1);
})->throws(InvalidArgumentException::class);

it('should set timeout success', function () {
    $transport = TransportBuilder::make()
        ->setClient(new GuzzleClient())
        ->setLogger(new NullLogger())
        ->build();

    $transport->setTimeout(10);

    expect($transport->getTimeout())->toBe(10);
});

it('can send a request', function () {
    $client = $this->createMock(ClientInterface::class);
    $client
        ->expects($this->once())
        ->method('sendRequest')
        ->willReturn(new Response(200, [], 'Hello World'));

    $transport = TransportBuilder::make()->setClient($client)->build();

    $request = new Request('GET', new Uri('https://example.com/api/v1/test'));

    $response = $transport->sendRequest($request);

    expect($response)->toBeInstanceOf(Response::class);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('Hello World');
});

it('can get the base URI', function () {
    $client = new GuzzleClient();

    $transport = TransportBuilder::make()->setClient($client)->build();

    expect($transport->getUri())->toBe('/');
});

it('can set the base URI', function () {
    $client = new GuzzleClient();

    $transport = TransportBuilder::make()->setClient($client)->build();

    $transport->setUri('https://example.com');

    expect($transport->getUri())->toBe('https://example.com');
});

it('can get the headers', function () {
    $transport = TransportBuilder::make()->build();

    expect($transport->getHeaders())->toBe([]);
});

it('can set the headers', function () {
    $transport = TransportBuilder::make()->build();

    $transport->setHeaders([
        'Content-Type' => 'application/json',
        'X-Tester' => 'Farzai',
    ]);

    expect($transport->getHeaders())->toBe([
        'Content-Type' => 'application/json',
        'X-Tester' => 'Farzai',
    ]);
});

it('can get the retries', function () {
    $transport = TransportBuilder::make()->build();

    expect($transport->getRetries())->toBe(0);
});

it('can set the retries', function () {
    $transport = TransportBuilder::make()->build();

    $transport->setRetries(3);

    expect($transport->getRetries())->toBe(3);
});

it('should throw an exception when retries is less than 0', function () {
    $transport = TransportBuilder::make()->build();

    $transport->setRetries(-1);
})->throws(InvalidArgumentException::class);

it('can get valid request options', function () {
    $transport = TransportBuilder::make()->build();

    $transport->setHeaders(
        $expectHeaders = [
            'Content-Type' => ['application/json'],
            'X-Tester' => ['Farzai'],
        ]
    );

    $transport->setUri('https://example.com');

    $request = new Request('POST', new Uri('/api/v1/test?foo=bar'));
    $resultRequest = $transport->setupRequest($request);

    expect('https://example.com/api/v1/test?foo=bar')->toBe(
        $resultRequest->getUri()->__toString()
    );
    expect('POST')->toBe($resultRequest->getMethod());
    expect('https')->toBe($resultRequest->getUri()->getScheme());

    // Headers
    expect('application/json')->toBe(
        $resultRequest->getHeaderLine('Content-Type')
    );
    expect('Farzai')->toBe($resultRequest->getHeaderLine('X-Tester'));

    // Expect headers
    expect(['Host' => ['example.com']] + $expectHeaders)->toBe(
        $resultRequest->getHeaders()
    );

    // Query
    expect('foo=bar')->toBe($resultRequest->getUri()->getQuery());
});
