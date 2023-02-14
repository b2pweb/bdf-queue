<?php

namespace Bdf\Queue\Destination\Queue;

use BadMethodCallException;
use Bdf\Queue\Connection\Factory\ResolverConnectionDriverFactory;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Memory\MemoryTopic;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\MultiQueueReader;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\AggregateDestination;
use Bdf\Queue\Destination\DestinationFactory;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Destination\Topic\TopicDestination;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Testing\StackMessagesReceiver;
use PHPUnit\Framework\TestCase;

class AggregateDestinationTest extends TestCase
{
    /**
     * @var MemoryConnection
     */
    private $connection;

    /**
     * @var MemoryTopic
     */
    private $topic;

    /**
     * @var AggregateDestination
     */
    private $destination;


    /**
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection();
        $this->destination = new AggregateDestination([
            new QueueDestination($this->connection->queue(), 'q1'),
            new QueueDestination($this->connection->queue(), 'q2'),
            new TopicDestination($this->topic = $this->connection->topic(), 't1'),
        ]);
    }

    /**
     *
     */
    public function test_consumer()
    {
        $this->expectException(BadMethodCallException::class);
        $receiver = $this->createMock(ReceiverInterface::class);

        $this->destination->consumer($receiver);
    }

    /**
     *
     */
    public function test_send()
    {
        $message = new Message('foo');

        $topicData = null;

        $this->topic->subscribe(['t1'], function ($message) use(&$topicData) {
            $topicData = $message->message()->data();
        });

        $this->assertInstanceOf(NullPromise::class, $this->destination->send($message));

        $this->topic->consume();

        $this->assertNull($message->queue());
        $this->assertEquals('foo', $this->connection->queue()->pop('q1')->message()->data());
        $this->assertEquals('foo', $this->connection->queue()->pop('q2')->message()->data());
        $this->assertEquals('foo', $topicData);
    }

    /**
     *
     */
    public function test_send_with_reply()
    {
        $this->expectException(BadMethodCallException::class);

        $message = new Message('foo');
        $message->setNeedsReply(true);

        $this->destination->send($message);
    }

    /**
     *
     */
    public function test_raw()
    {
        $topicData = null;

        $this->topic->subscribe(['t1'], function ($message) use(&$topicData) {
            $topicData = $message->message()->raw();
        });

        $this->destination->raw('foo');

        $this->topic->consume();

        $this->assertEquals('foo', $this->connection->queue()->pop('q1')->message()->raw());
        $this->assertEquals('foo', $this->connection->queue()->pop('q2')->message()->raw());
        $this->assertEquals('foo', $topicData);
    }

    /**
     *
     */
    public function test_declare_destroy()
    {
        $this->destination->declare();
        $this->assertEquals(['q1', 'q2'], $this->connection->getQueues());

        $this->destination->destroy();
        $this->assertEmpty($this->connection->getQueues());
    }
}
