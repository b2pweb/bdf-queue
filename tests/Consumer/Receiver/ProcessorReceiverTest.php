<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Message\InteractEnvelopeInterface;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Processor\ProcessorInterface;
use Bdf\Queue\Processor\ProcessorResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class ProcessorReceiverTest extends TestCase
{
    /** @var ProcessorResolverInterface|MockObject */
    protected $resolver;
    /** @var ConsumerInterface|MockObject */
    protected $consumer;

    /**
     * 
     */
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(ProcessorResolverInterface::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
    }

    /**
     *
     */
    public function test_deleted_job()
    {
        $this->resolver->expects($this->never())->method('resolve');

        $envelope = new QueueEnvelope($this->createMock(QueueDriverInterface::class), new QueuedMessage());
        $envelope->acknowledge();

        $receiver = new ProcessorReceiver($this->resolver);
        $receiver->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_basic_job()
    {
        $envelope = $this->createMock(InteractEnvelopeInterface::class);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())->method('process')->with($envelope);

        $this->resolver->expects($this->once())->method('resolve')->willReturn($processor);

        $receiver = new ProcessorReceiver($this->resolver);
        $receiver->receive($envelope, $this->consumer);
    }

    /**
     *
     */
    public function test_reject_job()
    {
        $this->expectException(\Exception::class);

        $envelope = $this->createMock(InteractEnvelopeInterface::class);
        $envelope->expects($this->once())->method('reject');

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())->method('process')->willThrowException(new \Exception('test'));
        $this->resolver->expects($this->once())->method('resolve')->willReturn($processor);

        $receiver = new ProcessorReceiver($this->resolver);
        $receiver->receive($envelope, $this->consumer);
    }
}