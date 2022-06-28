<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\Reader\QueueReader;
use Bdf\Queue\Consumer\Reader\QueueReaderInterface;
use Bdf\Queue\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class QueueConsumerTest extends TestCase
{
    /** @var QueueReaderInterface */
    private $reader;

    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * 
     */
    protected function setUp(): void
    {
        $this->driver = (new MemoryConnection())->queue();
        $this->reader = new QueueReader($this->driver, 'my-queue');
    }

    /**
     *
     */
    public function test_connection()
    {
        $receiver = $this->createMock(ReceiverInterface::class);
        $consumer = new QueueConsumer($this->reader, $receiver);

        $this->assertSame($this->driver->connection(), $consumer->connection());
    }

    /**
     *
     */
    public function test_sending_null()
    {
        $last = false;
        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('receiveTimeout')
            ->willReturnCallback(function($consumer) use (&$last) {
                $last = null;
                $consumer->stop();
            });

        $consumer = new QueueConsumer($this->reader, $receiver);
        $consumer->consume(0);

        $this->assertNull($last);
    }

    /**
     *
     */
    public function test_sending_message()
    {
        $this->driver->push((new Message('my message'))->setQueue('my-queue'));

        $last = false;
        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('receive')
            ->willReturnCallback(function($message, $consumer) use (&$last) {
                $last = $message;
                $consumer->stop();
            });

        $consumer = new QueueConsumer($this->reader, $receiver);
        $consumer->consume(0);

        $this->assertSame('my message', $last->message()->data());
    }

    /**
     *
     */
    public function test_ending_closes_connection()
    {
        $connection = new class extends MemoryConnection {
            public $closed = false;
            public function close(): void { $this->closed = true; }
        };

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('receiveTimeout')
            ->willReturnCallback(function($consumer) {
                $consumer->stop();
            });

        $consumer = new QueueConsumer(new QueueReader($connection->queue(), 'foo'), $receiver);
        $consumer->consume(0);

        $this->assertTrue($connection->closed);
    }

    /**
     *
     */
    public function test_stop_twice_should_call_receiveStop_only_once()
    {
        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('receiveStop');

        $consumer = new QueueConsumer($this->reader, $receiver);
        $consumer->stop();
        $consumer->stop();
    }
}