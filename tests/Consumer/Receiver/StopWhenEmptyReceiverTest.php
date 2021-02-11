<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class StopWhenEmptyReceiverTest extends TestCase
{
    /**
     * 
     */
    public function test_no_stop_if_not_empty()
    {
        $decorate = $this->createMock(ReceiverInterface::class);
        $decorate->expects($this->once())->method('receiveTimeout');

        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('The worker will stop for no consuming job');

        $extension = new StopWhenEmptyReceiver($decorate, $logger);
        $extension->receiveTimeout($consumer);
    }
}
