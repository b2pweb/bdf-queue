<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class RetryJobReceiverTest extends TestCase
{
    protected $logger;
    /** @var ReceiverInterface|MockObject */
    protected $extension;
    /** @var ConsumerInterface|MockObject */
    protected $consumer;

    /**
     *
     */
    public function setUp(): void
    {
        $this->logger = new NullLogger();
        $this->extension = $this->createMock(ReceiverInterface::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
    }

    /**
     *
     */
    public function test_retry()
    {
        $driver = $this->createMock(QueueDriverInterface::class);
        $driver->expects($this->once())->method('push');

        $message = new QueuedMessage();
        $message->setRaw('raw');
        $message->setQueue('queue');
        $envelope = new QueueEnvelope($driver, $message);

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $receiver = new RetryMessageReceiver($this->extension, $this->logger, 1);
        $receiver->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_max_tries()
    {
        $this->expectExceptionMessage('foo');

        $driver = $this->createMock(QueueDriverInterface::class);
        $driver->expects($this->never())->method('push');

        $message = new QueuedMessage();
        $message->setRaw('raw');
        $message->setQueue('queue');
        $message->setAttempts(2);
        $envelope = new QueueEnvelope($driver, $message);

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $receiver = new RetryMessageReceiver($this->extension, $this->logger, 1);
        $receiver->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_max_tries_from_job()
    {
        $driver = $this->createMock(QueueDriverInterface::class);
        $driver->expects($this->once())->method('push');

        $message = new QueuedMessage();
        $message->setRaw('raw');
        $message->setQueue('queue');
        $message->setAttempts(2);
        $message->setMaxTries(3);
        $envelope = new QueueEnvelope($driver, $message);

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $receiver = new RetryMessageReceiver($this->extension, $this->logger, 1);
        $receiver->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_start()
    {
        $this->extension->expects($this->once())->method('start')->with($this->consumer);

        $extension = new RetryMessageReceiver($this->extension, $this->logger, 1);
        $extension->start($this->consumer);
    }
}
