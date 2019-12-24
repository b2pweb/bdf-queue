<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class StackMessagesReceiverTest
 */
class StackMessagesReceiverTest extends TestCase
{
    /**
     *
     */
    public function test_empty()
    {
        $receiver = new StackMessagesReceiver();

        $this->assertCount(0, $receiver);
        $this->assertNull($receiver->last());
        $this->assertSame([], $receiver->messages());
    }

    /**
     *
     */
    public function test_receive()
    {
        $receiver = new StackMessagesReceiver();
        $consumer = $this->createMock(ConsumerInterface::class);

        $message = $this->createMock(EnvelopeInterface::class);
        $receiver->receive($message, $consumer);

        $this->assertCount(1, $receiver);
        $this->assertSame($message, $receiver->last());
        $this->assertSame([$message], $receiver->messages());
        $this->assertSame($message, $receiver[0]);
        $this->assertTrue(isset($receiver[0]));
        $this->assertFalse(isset($receiver[1]));

        $message2 = $this->createMock(EnvelopeInterface::class);
        $receiver->receive($message2, $consumer);

        $this->assertCount(2, $receiver);
        $this->assertSame($message2, $receiver->last());
        $this->assertSame([$message, $message2], $receiver->messages());
        $this->assertSame($message2, $receiver[1]);
        $this->assertTrue(isset($receiver[1]));
    }

    /**
     *
     */
    public function test_clear()
    {
        $receiver = new StackMessagesReceiver();
        $consumer = $this->createMock(ConsumerInterface::class);

        $receiver->receive($this->createMock(EnvelopeInterface::class), $consumer);
        $receiver->receive($this->createMock(EnvelopeInterface::class), $consumer);

        $this->assertCount(2, $receiver);
        $receiver->clear();
        $this->assertCount(0, $receiver);
    }
}
