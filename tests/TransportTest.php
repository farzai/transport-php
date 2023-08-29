<?php

use Farzai\Transport\Request;
use Farzai\Transport\Transport;
use Farzai\Transport\TransportBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

it('can send a request', function () {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
        ->method('sendRequest')
        ->willReturn(new Response(200, [], 'Hello World'));

    $transport = new Transport($client);

    $request = new Request('GET', new Uri('https://example.com'));

    $response = $transport->sendRequest($request);

    expect($response)->toBeInstanceOf(Response::class);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('Hello World');
});

it('can get the base URI', function () {
    $client = new Client();

    $transport = new Transport($client);

    expect($transport->getUri())->toBe('/');
});

it('can build transport with builder', function () {
    $transport = TransportBuilder::make()
        ->setClient(new Client())
        ->setLogger(new NullLogger())
        ->build();

    expect($transport)->toBeInstanceOf(Transport::class);

    expect($transport->getUri())->toBe('/');
});
