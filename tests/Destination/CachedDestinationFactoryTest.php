<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Destination\Queue\QueueDestination;
use PHPUnit\Framework\TestCase;

/**
 * Class CachedDestinationFactoryTest
 */
class CachedDestinationFactoryTest extends TestCase
{
    /**
     *
     */
    public function test_create()
    {
        $inner = new class implements DestinationFactoryInterface {
            public function create(string $destination): DestinationInterface
            {
                return new QueueDestination((new NullConnection(''))->queue(), '');
            }

            public function destinationNames(): array
            {
                return [];
            }
        };

        $factory = new CachedDestinationFactory($inner);

        $this->assertSame($factory->create('foo'), $factory->create('foo'));
        $this->assertNotSame($factory->create('foo'), $factory->create('bar'));
    }

    /**
     *
     */
    public function test_getting_destination_names()
    {
        $inner = $this->createMock(DestinationFactoryInterface::class);
        $inner->expects($this->any())->method('destinationNames')->willReturn(['foo']);

        $factory = new CachedDestinationFactory($inner);

        $this->assertSame(['foo'], $factory->destinationNames());
    }
}
