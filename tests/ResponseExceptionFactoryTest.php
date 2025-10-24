<?php

declare(strict_types=1);

use Farzai\Transport\Contracts\ResponseInterface;
use Farzai\Transport\Exceptions\BadResponseException;
use Farzai\Transport\Exceptions\ClientException;
use Farzai\Transport\Exceptions\HttpException;
use Farzai\Transport\Exceptions\ResponseExceptionFactory;
use Farzai\Transport\Exceptions\ServerException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

describe('ResponseExceptionFactory', function () {
    beforeEach(function () {
        $this->request = Mockery::mock(RequestInterface::class);
        $this->psrResponse = Mockery::mock(PsrResponseInterface::class);
    });

    it('creates ClientException for 4xx status codes', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(404);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('Not Found');
        $response->shouldReceive('getReasonPhrase')->andReturn('Not Found');

        $exception = ResponseExceptionFactory::create($response);

        expect($exception)->toBeInstanceOf(ClientException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class);
    });

    it('creates ClientException for all 4xx codes', function () {
        $codes = [400, 401, 403, 404, 422, 429, 499];

        foreach ($codes as $code) {
            $response = Mockery::mock(ResponseInterface::class);
            $response->shouldReceive('statusCode')->andReturn($code);
            $response->shouldReceive('getPsrRequest')->andReturn($this->request);
            $response->shouldReceive('jsonOrNull')->andReturn(null);
            $response->shouldReceive('body')->andReturn('Error');
            $response->shouldReceive('getReasonPhrase')->andReturn('Error');

            $exception = ResponseExceptionFactory::create($response);

            expect($exception)->toBeInstanceOf(ClientException::class);
        }
    });

    it('creates ServerException for 5xx status codes', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(500);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('Internal Server Error');
        $response->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');

        $exception = ResponseExceptionFactory::create($response);

        expect($exception)->toBeInstanceOf(ServerException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class);
    });

    it('creates ServerException for all 5xx codes', function () {
        $codes = [500, 501, 502, 503, 504, 599];

        foreach ($codes as $code) {
            $response = Mockery::mock(ResponseInterface::class);
            $response->shouldReceive('statusCode')->andReturn($code);
            $response->shouldReceive('getPsrRequest')->andReturn($this->request);
            $response->shouldReceive('jsonOrNull')->andReturn(null);
            $response->shouldReceive('body')->andReturn('Error');
            $response->shouldReceive('getReasonPhrase')->andReturn('Error');

            $exception = ResponseExceptionFactory::create($response);

            expect($exception)->toBeInstanceOf(ServerException::class);
        }
    });

    it('creates BadResponseException for non-2xx, non-4xx, non-5xx codes', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(300);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('Redirect');
        $response->shouldReceive('getReasonPhrase')->andReturn('Multiple Choices');

        $exception = ResponseExceptionFactory::create($response);

        expect($exception)->toBeInstanceOf(BadResponseException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class);
    });

    it('can chain previous exception', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(404);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('Not Found');
        $response->shouldReceive('getReasonPhrase')->andReturn('Not Found');

        $previous = new \RuntimeException('Previous error');
        $exception = ResponseExceptionFactory::create($response, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('extracts error message from JSON message field', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['message' => 'User not found']);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('User not found');
    });

    it('extracts error message from JSON error field', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['error' => 'Invalid credentials']);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Invalid credentials');
    });

    it('extracts error message from JSON error_message field', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['error_message' => 'Authentication failed']);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Authentication failed');
    });

    it('extracts error message from JSON error_msg field', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['error_msg' => 'Request timeout']);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Request timeout');
    });

    it('extracts error message from JSON error_description field', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['error_description' => 'Token expired']);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Token expired');
    });

    it('handles array of errors in JSON', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn([
            'errors' => ['Name is required', 'Email is invalid'],
        ]);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Name is required; Email is invalid');
    });

    it('filters empty values from error arrays', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn([
            'errors' => ['Valid error', '', null, 'Another error'],
        ]);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Valid error; Another error');
    });

    it('skips non-string error values', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn([
            'error' => 12345, // Not a string
        ]);
        $response->shouldReceive('body')->andReturn('Numeric error');
        $response->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Numeric error');
    });

    it('skips empty string error values', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn([
            'message' => '',
            'error' => 'Actual error',
        ]);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Actual error');
    });

    it('falls back to response body when JSON has no valid errors', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(['data' => 'some data']);
        $response->shouldReceive('body')->andReturn('Plain text error');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Plain text error');
    });

    it('uses response body for non-JSON responses', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('HTML error page');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('HTML error page');
    });

    it('truncates long response bodies', function () {
        $longBody = str_repeat('a', 600);
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn($longBody);
        $response->shouldReceive('statusCode')->andReturn(500);
        $response->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        // Should fall back to HTTP status message when body is too long
        expect($message)->toBe('HTTP 500 Internal Server Error');
    });

    it('uses HTTP status description when body is empty', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('');
        $response->shouldReceive('statusCode')->andReturn(404);
        $response->shouldReceive('getReasonPhrase')->andReturn('Not Found');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('HTTP 404 Not Found');
    });

    it('handles JSON parsing errors gracefully', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andThrow(new \Exception('Invalid JSON'));
        $response->shouldReceive('body')->andReturn('Error text');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Error text');
    });

    it('handles non-array JSON responses', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn('string response');
        $response->shouldReceive('body')->andReturn('Error text');

        $message = ResponseExceptionFactory::getErrorMessage($response);

        expect($message)->toBe('Error text');
    });

    it('respects error field priority', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('jsonOrNull')->andReturn([
            'error' => 'Lower priority',
            'message' => 'Higher priority',
            'error_description' => 'Lowest priority',
        ]);

        $message = ResponseExceptionFactory::getErrorMessage($response);

        // 'message' has highest priority
        expect($message)->toBe('Higher priority');
    });

    it('includes status code in exception', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(403);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(null);
        $response->shouldReceive('body')->andReturn('Forbidden');
        $response->shouldReceive('getReasonPhrase')->andReturn('Forbidden');

        $exception = ResponseExceptionFactory::create($response);

        expect($exception->getCode())->toBe(403);
    });

    it('creates exception with extracted message', function () {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('statusCode')->andReturn(400);
        $response->shouldReceive('getPsrRequest')->andReturn($this->request);
        $response->shouldReceive('jsonOrNull')->andReturn(['message' => 'Validation failed']);

        $exception = ResponseExceptionFactory::create($response);

        expect($exception->getMessage())->toBe('Validation failed');
    });
});
