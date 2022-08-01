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
     * @var NextInterface|MockObject
     */
    private $next;

    /**
     *
     */
    public function setUp(): void
    {
        $this->logger = new NullLogger();
        $this->extension = $this->createMock(ReceiverInterface::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
        $this->next = $this->createMock(NextInterface::class);
    }

    /**
     *
     */
    public function test_on_failure()
    {
        $this->expectExceptionMessage('foo');

        $this->next->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $driver = new NullConnection('test');
        $message = new QueuedMessage();
        $message->setConnection('test');
        $message->setRaw('raw');
        $message->setQueue('queue');
        $envelope = new QueueEnvelope($driver->queue(), $message);

        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->once())->method('store');

        $extension = new MessageStoreReceiver($failer, $this->logger);
        $extension->receive($envelope, $this->next);
    }

    /**
     *
     */
    public function test_on_failure_legacy()
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

        $this->next->expects($this->once())->method('receive')->willThrowException(new \Exception('foo'));

        $driver = new NullConnection('test');
        $message = new QueuedMessage();
        $message->setConnection($driver->getName());
        $message->setRaw('raw');
        $message->setQueue('queue');
        $message->disableStore();

        $envelope = new QueueEnvelope($driver->queue(), $message);

        $failer = $this->createMock(FailedJobStorageInterface::class);
        $failer->expects($this->never())->method('store');

        $extension = new MessageStoreReceiver($failer, $this->logger);
        $extension->receive($envelope, $this->next);
    }

    /**
     *
     */
    public function test_disable_failure_legacy()
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
        $this->next->expects($this->once())->method('receive')
            ->with(null, $this->next);

        $extension = new MessageStoreReceiver($this->createMock(FailedJobStorageInterface::class), $this->logger);
        $extension->receive(null, $this->next);
    }

    /**
     *
     */
    public function test_delegation_legacy()
    {
        $this->extension->expects($this->once())->method('receive')
            ->with(null, $this->consumer);

        $extension = new MessageStoreReceiver($this->extension, $this->createMock(FailedJobStorageInterface::class), $this->logger);
        $extension->receive(null, $this->consumer);
    }

    /**
     *
     */
    public function test_start()
    {
        $this->next->expects($this->once())->method('start')->with($this->next);

        $extension = new MessageStoreReceiver($this->createMock(FailedJobStorageInterface::class), $this->logger);
        $extension->start($this->next);
    }

    /**
     *
     */
    public function test_start_legacy()
    {
        $this->extension->expects($this->once())->method('start')->with($this->consumer);

        $extension = new MessageStoreReceiver($this->extension, $this->createMock(FailedJobStorageInterface::class), $this->logger);
        $extension->start($this->consumer);
    }
}
