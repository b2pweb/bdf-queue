<?php

namespace Bdf\Queue\Connection\Null;

use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Null
 */
class NullTopicTest extends TestCase
{
    /**
     * 
     */
    public function test_unused_methods()
    {
        $connection = new NullConnection('foo');
        $driver = $connection->topic();

        $this->assertSame($connection, $driver->connection());

        $driver->publish(new Message());
        $driver->publishRaw('topic', 'message');
        $this->assertSame(0, $driver->consume());
        $driver->subscribe(['topic'], function(){});
    }
}