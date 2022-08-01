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
class TimeLimiterReceiverTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function test_receiver_stops_when_time_limit_is_reached()
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->once())->method('receive')->willReturnCallback(function() {
            sleep(2);
        });

        $next->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to time limit of {timeLimit}s reached', ['timeLimit' => 1]);

        $extension = new TimeLimiterReceiver(1, $logger);
        $extension->start($next);
        $extension->receive('message', $next);
    }

    /**
     * @group time-sensitive
     */
    public function test_receiver_stops_when_time_limit_is_reached_legacy()
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->once())->method('receive')->willReturnCallback(function() {
            sleep(2);
        });

        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to time limit of {timeLimit}s reached', ['timeLimit' => 1]);

        $extension = new TimeLimiterReceiver($decorated, 1, $logger);
        $extension->start($consumer);
        $extension->receive('message', $consumer);
    }
}
