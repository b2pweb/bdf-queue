<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\MultiQueueReader;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Testing\StackMessagesReceiver;
use PHPUnit\Framework\TestCase;

/**
 * Class MultiQueueDestinationTest
 */
class MultiQueueDestinationTest extends TestCase
{
    /**
     * @var MemoryConnection
     */
    private $connection;

    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * @var MultiQueueDestination
     */
    private $destination;


    /**
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection();
        $this->driver = $this->connection->queue();
        $this->destination = new MultiQueueDestination($this->driver, ['q1', 'q2', 'q3']);
    }

    /**
     *
     */
    public function test_consumer()
    {
        $receiver = $this->createMock(ReceiverInterface::class);

        $this->assertEquals(
            new QueueConsumer(new MultiQueueReader($this->driver, ['q1', 'q2', 'q3']), $receiver),
            $this->destination->consumer($receiver)
        );
    }

    /**
     *
     */
    public function test_consumer_functional()
    {
        $receiver = new StackMessagesReceiver();
        $consumer = $this->destination->consumer(new StopWhenEmptyReceiver($receiver));

        $this->driver->push((new Message('foo'))->setQueue('q1'));
        $this->driver->push((new Message('bar'))->setQueue('q3'));

        $consumer->consume(0);

        $this->assertCount(2, $receiver);
        $this->assertEquals('foo', $receiver[0]->message()->data());
        $this->assertEquals('bar', $receiver[1]->message()->data());
    }

    /**
     *
     */
    public function test_send()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->destination->send(new Message());
    }

    /**
     *
     */
    public function test_raw()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->destination->raw('');
    }

    /**
     *
     */
    public function test_declare_destroy()
    {
        $this->destination->declare();
        $this->assertEquals(['q1', 'q2', 'q3'], $this->connection->getQueues());

        $this->destination->destroy();
        $this->assertEmpty($this->connection->getQueues());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_declare_destroy_not_manageable()
    {
        $destination = new MultiQueueDestination((new NullConnection(''))->queue(), ['q1', 'q2', 'q3']);

        $destination->declare();
        $destination->destroy();
    }
}
