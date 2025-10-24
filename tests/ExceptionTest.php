<?php

declare(strict_types=1);

use Farzai\Transport\Exceptions\BadResponseException;
use Farzai\Transport\Exceptions\ClientException;
use Farzai\Transport\Exceptions\HttpException;
use Farzai\Transport\Exceptions\NetworkException;
use Farzai\Transport\Exceptions\RequestException;
use Farzai\Transport\Exceptions\SerializationException;
use Farzai\Transport\Exceptions\ServerException;
use Farzai\Transport\Exceptions\TimeoutException;
use Farzai\Transport\Exceptions\TransportException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

describe('HttpException', function () {
    it('can be created with request and response', function () {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('GET');
        $request->shouldReceive('getUri')->andReturn(new \Nyholm\Psr7\Uri('https://example.com/api'));
        $request->shouldReceive('getHeaders')->andReturn(['Accept' => ['application/json']]);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(500);
        $response->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');
        $response->shouldReceive('getHeaders')->andReturn(['Content-Type' => ['application/json']]);

        $exception = new HttpException('Server error', $request, $response);

        expect($exception->getMessage())->toBe('Server error')
            ->and($exception->getRequest())->toBe($request)
            ->and($exception->getResponse())->toBe($response)
            ->and($exception->hasResponse())->toBeTrue()
            ->and($exception->getStatusCode())->toBe(500);
    });

    it('can be created without response', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new HttpException('Network error', $request);

        expect($exception->getRequest())->toBe($request)
            ->and($exception->getResponse())->toBeNull()
            ->and($exception->hasResponse())->toBeFalse()
            ->and($exception->getStatusCode())->toBeNull();
    });

    it('implements PSR RequestExceptionInterface', function () {
        $request = Mockery::mock(RequestInterface::class);
        $exception = new HttpException('Error', $request);

        expect($exception)->toBeInstanceOf(RequestExceptionInterface::class);
    });

    it('provides detailed error context', function () {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('POST');
        $request->shouldReceive('getUri')->andReturn(new \Nyholm\Psr7\Uri('https://api.example.com/users'));
        $request->shouldReceive('getHeaders')->andReturn(['Authorization' => ['Bearer token']]);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(401);
        $response->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');
        $response->shouldReceive('getHeaders')->andReturn(['WWW-Authenticate' => ['Bearer']]);

        $exception = new HttpException('Authentication failed', $request, $response);
        $context = $exception->getContext();

        expect($context)->toHaveKey('message')
            ->and($context)->toHaveKey('request')
            ->and($context)->toHaveKey('response')
            ->and($context['request']['method'])->toBe('POST')
            ->and($context['request']['uri'])->toBe('https://api.example.com/users')
            ->and($context['response']['status_code'])->toBe(401);
    });

    it('provides context without response', function () {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('GET');
        $request->shouldReceive('getUri')->andReturn(new \Nyholm\Psr7\Uri('https://example.com'));
        $request->shouldReceive('getHeaders')->andReturn([]);

        $exception = new HttpException('Error', $request);
        $context = $exception->getContext();

        expect($context)->toHaveKey('message')
            ->and($context)->toHaveKey('request')
            ->and($context)->not->toHaveKey('response');
    });

    it('can chain previous exceptions', function () {
        $request = Mockery::mock(RequestInterface::class);
        $previous = new \RuntimeException('Original error');

        $exception = new HttpException('Wrapped error', $request, null, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('can have custom error code', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new HttpException('Error', $request, null, null, 1234);

        expect($exception->getCode())->toBe(1234);
    });
});

describe('RequestException', function () {
    it('can be created with request', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new RequestException('Request failed', $request);

        expect($exception->getMessage())->toBe('Request failed')
            ->and($exception->request)->toBe($request)
            ->and($exception)->toBeInstanceOf(TransportException::class);
    });

    it('can chain previous exceptions', function () {
        $request = Mockery::mock(RequestInterface::class);
        $previous = new \RuntimeException('Network error');

        $exception = new RequestException('Request failed', $request, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('sets error code to zero', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new RequestException('Request failed', $request);

        expect($exception->getCode())->toBe(0);
    });
});

describe('NetworkException', function () {
    it('extends RequestException', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new NetworkException('Network timeout', $request);

        expect($exception)->toBeInstanceOf(RequestException::class)
            ->and($exception->getMessage())->toBe('Network timeout')
            ->and($exception->request)->toBe($request);
    });

    it('can chain previous exceptions', function () {
        $request = Mockery::mock(RequestInterface::class);
        $previous = new \RuntimeException('Connection refused');

        $exception = new NetworkException('Network error', $request, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });
});

describe('TimeoutException', function () {
    it('stores timeout duration', function () {
        $request = Mockery::mock(RequestInterface::class);

        $exception = new TimeoutException('Request timeout', $request, 30);

        expect($exception)->toBeInstanceOf(RequestException::class)
            ->and($exception->getMessage())->toBe('Request timeout')
            ->and($exception->request)->toBe($request)
            ->and($exception->timeoutSeconds)->toBe(30);
    });

    it('can chain previous exceptions', function () {
        $request = Mockery::mock(RequestInterface::class);
        $previous = new \RuntimeException('Timeout error');

        $exception = new TimeoutException('Request timeout', $request, 60, $previous);

        expect($exception->getPrevious())->toBe($previous)
            ->and($exception->timeoutSeconds)->toBe(60);
    });
});

describe('BadResponseException', function () {
    it('extends HttpException', function () {
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $exception = new BadResponseException('Bad response', $request, $response);

        expect($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getMessage())->toBe('Bad response');
    });
});

describe('ClientException', function () {
    it('extends BadResponseException', function () {
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $exception = new ClientException('Client error', $request, $response);

        expect($exception)->toBeInstanceOf(BadResponseException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getMessage())->toBe('Client error');
    });
});

describe('ServerException', function () {
    it('extends BadResponseException', function () {
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $exception = new ServerException('Server error', $request, $response);

        expect($exception)->toBeInstanceOf(BadResponseException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getMessage())->toBe('Server error');
    });
});

describe('SerializationException', function () {
    it('extends TransportException', function () {
        $exception = new SerializationException('Serialization failed');

        expect($exception)->toBeInstanceOf(TransportException::class)
            ->and($exception->getMessage())->toBe('Serialization failed');
    });

    it('can store data and format', function () {
        $exception = new SerializationException(
            'Invalid data',
            '{"invalid": json}',
            18,
            'JSON'
        );

        expect($exception->data)->toBe('{"invalid": json}')
            ->and($exception->dataSize)->toBe(18)
            ->and($exception->format)->toBe('JSON')
            ->and($exception->getMessage())->toBe('Invalid data');
    });

    it('accepts null data', function () {
        $exception = new SerializationException('Error');

        expect($exception->data)->toBeNull()
            ->and($exception->dataSize)->toBe(0)
            ->and($exception->format)->toBe('unknown');
    });

    it('can chain previous exceptions', function () {
        $previous = new \RuntimeException('Parse error');
        $exception = new SerializationException(
            'Serialization failed',
            '{"data": "value"}',
            16,
            'JSON',
            $previous
        );

        expect($exception->getPrevious())->toBe($previous)
            ->and($exception->data)->toBe('{"data": "value"}');
    });

    it('can get truncated data', function () {
        $longData = str_repeat('a', 300);
        $exception = new SerializationException('Error', $longData, 300);

        $truncated = $exception->getTruncatedData(50);

        expect($truncated)->toContain('...')
            ->and(strlen($truncated))->toBeLessThan(300);
    });

    it('returns full data when under max length', function () {
        $shortData = 'short data';
        $exception = new SerializationException('Error', $shortData);

        $truncated = $exception->getTruncatedData(50);

        expect($truncated)->toBe($shortData);
    });

    it('returns null when no data', function () {
        $exception = new SerializationException('Error');

        expect($exception->getTruncatedData())->toBeNull();
    });
});

describe('TransportException', function () {
    it('is a RuntimeException', function () {
        $exception = new TransportException('Transport error');

        expect($exception)->toBeInstanceOf(\RuntimeException::class)
            ->and($exception->getMessage())->toBe('Transport error');
    });

    it('can have error code', function () {
        $exception = new TransportException('Error', 500);

        expect($exception->getCode())->toBe(500);
    });

    it('can chain previous exceptions', function () {
        $previous = new \Exception('Original error');
        $exception = new TransportException('Transport error', 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });
});
