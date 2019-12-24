<?php

namespace Bdf\Queue\Connection\Null;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Null
 */
class NullConnectionTest extends TestCase
{
    /**
     * 
     */
    public function test_unused_methods()
    {
        $driver = new NullConnection('foo');
        $driver->setConfig([]);
        $driver->close();

        $this->assertSame('foo', $driver->getName());
    }
}