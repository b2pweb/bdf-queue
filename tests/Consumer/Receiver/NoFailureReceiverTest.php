<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class NoFailureReceiverTest extends TestCase
{
    /**
     * 
     */
    public function test_next_call()
    {
        $consumer = $this->createMock(ConsumerInterface::class);

        $extension = $this->createMock(ReceiverInterface::class);
        $extension->expects($this->once())->method('receive')->with('foo', $consumer);

        $extension = new NoFailureReceiver($extension);
        $extension->receive('foo', $consumer);
    }

    /**
     *
     */
    public function test_no_error()
    {
        $extension = $this->createMock(ReceiverInterface::class);
        $extension->expects($this->once())->method('receive')->willThrowException(new \Exception('error'));

        $extension = new NoFailureReceiver($extension);
        $extension->receive('foo', $this->createMock(ConsumerInterface::class));
    }
}
