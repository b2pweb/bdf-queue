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
        };

        $factory = new CachedDestinationFactory($inner);

        $this->assertSame($factory->create('foo'), $factory->create('foo'));
        $this->assertNotSame($factory->create('foo'), $factory->create('bar'));
    }
}
