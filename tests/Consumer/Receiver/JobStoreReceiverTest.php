<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class JobStoreReceiverTest extends TestCase
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
    public function test_on_failure()
    {
        $this->expectExceptionMessage('foo');

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $driver = new NullConnection('test');
        $message = new QueuedMessage();
        $message->setConnection('test');
        $message->setRaw('raw');
        $message->setQueue('queue');
        $envelope = new QueueEnvelope($driver->queue(), $message);

        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->once())->method('store');

        $extension = new MessageStoreReceiver($this->extension, $failer, $this->logger);
        $extension->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_disable_failure()
    {
        $this->expectExceptionMessage('foo');

        $this->extension->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $driver = new NullConnection('test');
        $message = new QueuedMessage();
        $message->setConnection($driver->getName());
        $message->setRaw('raw');
        $message->setQueue('queue');
        $message->disableStore();

        $envelope = new QueueEnvelope($driver->queue(), $message);

        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->never())->method('store');

        $extension = new MessageStoreReceiver($this->extension, $failer, $this->logger);
        $extension->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_delegation()
    {
        $this->extension->expects($this->once())->method('receive')
            ->with(null, $this->consumer);

        $extension = new MessageStoreReceiver($this->extension, $this->createMock(FailedJobStorageInterface::class), $this->logger);
        $extension->receive(null, $this->consumer);
    }
}
