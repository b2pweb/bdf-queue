<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Driver
 * @group Bdf_Queue_Driver_Memory
 */
class MemoryConnectionTest extends TestCase
{
    /**
     * @var MemoryConnection
     */
    protected $connection;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection('foo', new JsonSerializer());
    }

    /**
     * 
     */
    public function test_instance()
    {
        $this->assertInstanceOf(Storage::class, $this->connection->storage());
        $this->assertInstanceOf(MemoryQueue::class, $this->connection->queue());
        $this->assertInstanceOf(MemoryTopic::class, $this->connection->topic());
    }

    /**
     *
     */
    public function test_declare_delete_queue()
    {
        $this->connection->declareQueue('foo');

        $this->assertSame(['foo'], $this->connection->getQueues());

        $this->connection->deleteQueue('foo');

        $this->assertSame([], $this->connection->getQueues());
    }

    /**
     *
     */
    public function test_declare_delete_topic()
    {
        $this->connection->declareTopic('topic');
        $driver = $this->connection->topic();

        $driver->subscribe(['topic', 'other'], function() {});
        $driver->publish(Message::createForTopic('topic', ['content'], 'TestEvent'));
        $driver->publish(Message::createForTopic('topic', ['content'], 'TestEvent'));
        $driver->publish(Message::createForTopic('other', ['content'], 'TestEvent'));

        $this->assertSame(3, $driver->awaiting());

        $this->connection->deleteTopic('topic');
        $this->assertSame(1, $driver->awaiting());
    }

    /**
     *
     */
    public function test_unused_methods()
    {
        $this->assertNull($this->connection->setConfig([]));
        $this->assertNull($this->connection->close());
    }
}