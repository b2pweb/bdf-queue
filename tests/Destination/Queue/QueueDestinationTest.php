<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\QueueConsumer;
use Bdf\Queue\Consumer\Reader\BufferedReader;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * Class QueueDestinationTest
 */
class QueueDestinationTest extends TestCase
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
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection();
        $this->driver = $this->connection->queue();
    }

    /**
     *
     */
    public function test_send()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');
        $message = new Message('foo');

        $this->assertInstanceOf(NullPromise::class, $destination->send($message));

        $this->assertEquals('my-queue', $message->queue());
        $this->assertEquals('foo', $this->driver->pop('my-queue')->message()->data());
    }

    /**
     *
     */
    public function test_send_with_needsReply()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');
        $message = new Message('foo');
        $message->setNeedsReply();

        $promise = $destination->send($message);

        $this->assertInstanceOf(QueuePromise::class, $promise);
        $this->assertEquals('my-queue', $message->queue());
        $this->assertEquals('my-queue_reply', $message->header('replyTo'));
        $this->assertNotEmpty($message->header('correlationId'));
        $this->assertEquals('foo', $this->driver->pop('my-queue')->message()->data());

        $this->driver->push((new Message('reply'))->setQueue('my-queue_reply')->addHeader('correlationId', $message->header('correlationId')));
        $this->assertEquals('reply', $promise->await()->data());
    }

    /**
     *
     */
    public function test_raw()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');

        $destination->raw('foo');

        $this->assertEquals('foo', $this->driver->pop('my-queue')->message()->raw());
    }

    /**
     *
     */
    public function test_declare()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');
        $destination->declare();

        $this->assertContains('my-queue', $this->connection->getQueues());
    }

    /**
     *
     */
    public function test_destroy()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');
        $destination->send(new Message());
        $destination->destroy();

        $this->assertNull($this->driver->pop('my-queue', 0));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_declare_destroy_not_manageable()
    {
        $destination = new QueueDestination((new NullConnection(''))->queue(), 'my-queue');

        $destination->declare();
        $destination->destroy();
    }

    /**
     *
     */
    public function test_consumer()
    {
        $destination = new QueueDestination($this->driver, 'my-queue');

        $receiver = $this->createMock(ReceiverInterface::class);
        $consumer = $destination->consumer($receiver);

        $this->assertInstanceOf(QueueConsumer::class, $consumer);

        $destination->send(new Message('foo'));

        $receiver->expects($this->once())
            ->method('receive')
            ->willReturnCallback(function ($message, $consumer) use(&$last) {
                $last = $message;
                $consumer->stop();
            })
        ;

        $consumer->consume(0);

        $this->assertInstanceOf(QueueEnvelope::class, $last);
        $this->assertEquals('foo', $last->message()->data());
    }

    /**
     *
     */
    public function test_consumer_prefetch()
    {
        $receiver = $this->createMock(ReceiverInterface::class);

        $this->assertEquals(
            new QueueConsumer(new BufferedReader($this->driver, 'my-queue', 10), $receiver),
            (new QueueDestination($this->driver, 'my-queue', 10))->consumer($receiver)
        );
    }
}
