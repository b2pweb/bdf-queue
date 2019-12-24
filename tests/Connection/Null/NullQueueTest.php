<?php

namespace Bdf\Queue\Connection\Null;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Null
 */
class NullQueueTest extends TestCase
{
    /**
     * 
     */
    public function test_unused_methods()
    {
        $connection = new NullConnection('foo');
        $driver = $connection->queue();
        
        $stats = [];

        $this->assertSame($connection, $driver->connection());
        $this->assertSame(null, $driver->pop('queue', 0));
        $this->assertSame(0, $driver->count('queue'));
        $this->assertSame($stats, $driver->stats());

        
        $driver->push(new Message());
        $driver->pushRaw('job', 'queue');
        $driver->acknowledge(new QueuedMessage());
        $driver->release(new QueuedMessage());
    }
}