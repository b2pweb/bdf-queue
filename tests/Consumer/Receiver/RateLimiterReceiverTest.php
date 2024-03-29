<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class RateLimiterSubscriberTest extends TestCase
{
    protected $buffer;
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
    public function test_dont_sleep()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->never())->method('sleep');
        $receiver->receive('foo', $this->next);
    }

    /**
     *
     */
    public function test_dont_sleep_legacy()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->extension, $this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->never())->method('sleep');
        $receiver->receive('foo', $this->consumer);
    }

    /**
     *
     */
    public function test_dont_sleep_on_timeout()
    {
        /** @var RateLimiterReceiver $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->never())->method('sleep');
        $receiver->receiveTimeout($this->next);
    }

    /**
     *
     */
    public function test_dont_sleep_on_timeout_legacy()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->extension, $this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->never())->method('sleep');
        $receiver->receiveTimeout($this->consumer);
    }

    /**
     *
     */
    public function test_should_sleep()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->logger, 1, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->once())->method('sleep');
        $receiver->receive('foo', $this->next);
    }

    /**
     *
     */
    public function test_should_sleep_legacy()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->extension, $this->logger, 1, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->once())->method('sleep');
        $receiver->receive('foo', $this->consumer);
    }

    /**
     *
     */
    public function test_should_sleep_and_reset()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->once())->method('sleep');
        $receiver->receive('foo', $this->next);
        $receiver->receive('foo', $this->next);
        $receiver->receive('foo', $this->next);
    }

    /**
     *
     */
    public function test_should_sleep_and_reset_legacy()
    {
        /** @var RateLimiterReceiver::class $receiver */
        $receiver = $this->getMockBuilder(RateLimiterReceiver::class)
            ->setConstructorArgs([$this->extension, $this->logger, 2, 0])
            ->enableProxyingToOriginalMethods()
            ->setMethods(['sleep'])
            ->getMock();
        $receiver->expects($this->once())->method('sleep');
        $receiver->receive('foo', $this->consumer);
        $receiver->receive('foo', $this->consumer);
        $receiver->receive('foo', $this->consumer);
    }

    /**
     *
     */
    public function test_start()
    {
        $this->next->expects($this->once())->method('start')->with($this->next);

        $extension = new RateLimiterReceiver($this->logger, 1);
        $extension->start($this->next);
    }

    /**
     *
     */
    public function test_start_legacy()
    {
        $this->extension->expects($this->once())->method('start')->with($this->consumer);

        $extension = new RateLimiterReceiver($this->extension, $this->logger, 1);
        $extension->start($this->consumer);
    }
}
