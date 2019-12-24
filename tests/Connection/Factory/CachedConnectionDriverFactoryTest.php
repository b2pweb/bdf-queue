<?php

namespace Bdf\Queue\Connection\Factory;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CachedConnectionDriverFactoryTest
 */
class CachedConnectionDriverFactoryTest extends TestCase
{
    /**
     *
     */
    public function test_create()
    {
        $inner = $this->createMock(ConnectionDriverFactoryInterface::class);
        $driver = $this->createMock(ConnectionDriverInterface::class);

        $inner->expects($this->once())->method('create')->with('my-connection')->willReturn($driver);

        $factory = new CachedConnectionDriverFactory($inner);

        $this->assertSame($driver, $factory->create('my-connection'));
        $this->assertSame($factory->create('my-connection'), $factory->create('my-connection'));
    }

    /**
     *
     */
    public function test_defaultConnection()
    {
        $inner = $this->createMock(ConnectionDriverFactoryInterface::class);
        $driver = $this->createMock(ConnectionDriverInterface::class);

        $inner->expects($this->any())->method('defaultConnectionName')->willReturn('my-connection');
        $inner->expects($this->once())->method('create')->with('my-connection')->willReturn($driver);

        $factory = new CachedConnectionDriverFactory($inner);

        $this->assertSame($driver, $factory->create('my-connection'));
        $this->assertSame($factory->create('my-connection'), $factory->defaultConnection());
        $this->assertEquals('my-connection', $factory->defaultConnectionName());
    }
}
