<?php

declare(strict_types=1);

use Farzai\Transport\Factory\ClientFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

describe('ClientFactory', function () {
    it('can create client with auto-detection', function () {
        $client = ClientFactory::create();

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('can create client with logger', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once();

        $client = ClientFactory::create($logger);

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('can create client without logger', function () {
        $client = ClientFactory::create(null);

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('can detect Guzzle client when available', function () {
        if (! class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is not installed');
        }

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->withArgs(function ($message) {
            return str_contains($message, 'Guzzle') || str_contains($message, 'Symfony');
        })->once();

        $client = ClientFactory::create($logger);

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('can detect Symfony client when available', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->withArgs(function ($message) {
            return str_contains($message, 'Symfony');
        })->once();

        $client = ClientFactory::create($logger);

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('createGuzzle returns Guzzle client', function () {
        if (! class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is not installed');
        }

        $client = ClientFactory::createGuzzle();

        expect($client)->toBeInstanceOf(ClientInterface::class)
            ->and($client)->toBeInstanceOf(\GuzzleHttp\Client::class);
    });

    it('createGuzzle accepts configuration', function () {
        if (! class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is not installed');
        }

        $config = [
            'timeout' => 60,
            'verify' => false,
        ];

        $client = ClientFactory::createGuzzle($config);

        expect($client)->toBeInstanceOf(\GuzzleHttp\Client::class);
    });

    it('createGuzzle throws exception when not installed', function () {
        if (class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is installed, cannot test failure case');
        }

        expect(fn () => ClientFactory::createGuzzle())
            ->toThrow(RuntimeException::class, 'Guzzle HTTP client is not installed');
    });

    it('createSymfony returns Symfony client', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $client = ClientFactory::createSymfony();

        expect($client)->toBeInstanceOf(ClientInterface::class)
            ->and($client)->toBeInstanceOf(\Symfony\Component\HttpClient\Psr18Client::class);
    });

    it('createSymfony accepts options', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $options = [
            'timeout' => 60,
            'max_redirects' => 5,
        ];

        $client = ClientFactory::createSymfony($options);

        expect($client)->toBeInstanceOf(\Symfony\Component\HttpClient\Psr18Client::class);
    });

    it('createSymfony works with empty options', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $client = ClientFactory::createSymfony([]);

        expect($client)->toBeInstanceOf(\Symfony\Component\HttpClient\Psr18Client::class);
    });

    it('createSymfony throws exception when not installed', function () {
        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is installed, cannot test failure case');
        }

        expect(fn () => ClientFactory::createSymfony())
            ->toThrow(RuntimeException::class, 'Symfony HTTP Client is not installed');
    });

    it('isAvailable checks for Guzzle', function () {
        $isAvailable = ClientFactory::isAvailable('guzzle');

        expect($isAvailable)->toBeBool();

        if (class_exists('GuzzleHttp\Client')) {
            expect($isAvailable)->toBeTrue();
        } else {
            expect($isAvailable)->toBeFalse();
        }
    });

    it('isAvailable checks for Symfony', function () {
        $isAvailable = ClientFactory::isAvailable('symfony');

        expect($isAvailable)->toBeBool();

        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            expect($isAvailable)->toBeTrue();
        } else {
            expect($isAvailable)->toBeFalse();
        }
    });

    it('isAvailable is case insensitive', function () {
        $guzzleUpper = ClientFactory::isAvailable('GUZZLE');
        $guzzleLower = ClientFactory::isAvailable('guzzle');
        $symfonyUpper = ClientFactory::isAvailable('SYMFONY');
        $symfonyLower = ClientFactory::isAvailable('symfony');

        expect($guzzleUpper)->toBe($guzzleLower)
            ->and($symfonyUpper)->toBe($symfonyLower);
    });

    it('isAvailable checks for custom FQCN', function () {
        $isAvailable = ClientFactory::isAvailable(\stdClass::class);

        expect($isAvailable)->toBeTrue();

        $notAvailable = ClientFactory::isAvailable('NonExistent\Class\Name');

        expect($notAvailable)->toBeFalse();
    });

    it('getDetectedClientName returns client class name', function () {
        $clientName = ClientFactory::getDetectedClientName();

        expect($clientName)->toBeString()
            ->and(class_exists($clientName))->toBeTrue();
    });

    it('getDetectedClientName returns Symfony when available', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $clientName = ClientFactory::getDetectedClientName();

        // Symfony has priority over Guzzle
        expect($clientName)->toBe(\Symfony\Component\HttpClient\Psr18Client::class);
    });

    it('getDetectedClientName returns Guzzle when Symfony not available', function () {
        if (! class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is not installed');
        }

        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony has priority, cannot test Guzzle fallback');
        }

        $clientName = ClientFactory::getDetectedClientName();

        expect($clientName)->toBe(\GuzzleHttp\Client::class);
    });

    it('auto-detection prefers Symfony over Guzzle', function () {
        if (! class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony HTTP Client is not installed');
        }

        $client = ClientFactory::create();

        expect($client)->toBeInstanceOf(\Symfony\Component\HttpClient\Psr18Client::class);
    });

    it('falls back to Guzzle when Symfony not available', function () {
        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            $this->markTestSkipped('Symfony is installed, cannot test Guzzle fallback');
        }

        if (! class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('GuzzleHttp\Client is not installed');
        }

        $client = ClientFactory::create();

        expect($client)->toBeInstanceOf(\GuzzleHttp\Client::class);
    });

    it('uses PSR-18 discovery as last resort', function () {
        // This test will pass if any PSR-18 client is available
        $client = ClientFactory::create();

        expect($client)->toBeInstanceOf(ClientInterface::class);
    });
});
