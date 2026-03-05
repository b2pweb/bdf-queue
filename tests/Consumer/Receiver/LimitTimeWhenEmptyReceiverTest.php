<?php

namespace Bdf\Queue\Consumer\Receiver\Tests;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\Receiver\LimitTimeWhenEmptyReceiver;
use Bdf\Queue\Consumer\Receiver\NextInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 *
 */
class LimitTimeWhenEmptyReceiverTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function test_receiver_never_stop_stops()
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->never())->method('stop');

        $extension = new LimitTimeWhenEmptyReceiver(2);
        $extension->receiveTimeout($next);
        $extension->receiveTimeout($next);
        sleep(2);
        $extension->receive('message', $next);
        $extension->receiveTimeout($next);
    }

    /**
     * @group time-sensitive
     */
    public function test_receiver_stops_when_time_limit_is_reached()
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to empty time limit of {timeLimit}s reached', ['timeLimit' => 1]);

        $extension = new LimitTimeWhenEmptyReceiver(1, $logger);
        $extension->receiveTimeout($next);
        sleep(2);
        $extension->receiveTimeout($next);
    }

    /**
     * @group time-sensitive
     */
    public function test_receiver_stops_when_time_limit_is_reached_legacy()
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->any())->method('receiveTimeout');

        $next = $this->createMock(ConsumerInterface::class);
        $next->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to empty time limit of {timeLimit}s reached', ['timeLimit' => 1]);

        $extension = new LimitTimeWhenEmptyReceiver($decorated, 1, $logger);
        $extension->receiveTimeout($next);
        sleep(2);
        $extension->receiveTimeout($next);
    }
}
