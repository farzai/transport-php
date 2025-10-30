<?php

declare(strict_types=1);

namespace Farzai\Transport\Tests\Helpers;

use Farzai\Transport\Transport;
use Farzai\Transport\TransportBuilder;
use Psr\Http\Client\ClientInterface;

/**
 * Trait for creating transport instances in tests.
 */
trait CreatesTransport
{
    protected function createTransport(?ClientInterface $client = null): Transport
    {
        $builder = TransportBuilder::make();

        if ($client !== null) {
            $builder = $builder->setClient($client);
        }

        return $builder->build();
    }

    protected function createTransportWithMock(int $status = 200, string $body = ''): Transport
    {
        $client = MockClientBuilder::create()
            ->willReturn($status, [], $body)
            ->build();

        return $this->createTransport($client);
    }
}
