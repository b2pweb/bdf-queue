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
class MessageCountLimiterReceiverTest extends TestCase
{
    /**
     * @dataProvider countProvider
     */
    public function test_receiver_stops_when_maximum_count_exceeded($max, $shouldStop)
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->atLeastOnce())->method('receive');

        if (true === $shouldStop) {
            $next->expects($this->atLeastOnce())->method('stop');
        } else {
            $next->expects($this->never())->method('stop');
        }

        $extension = new MessageCountLimiterReceiver($max);
        $extension->receive('message1', $next);
        $extension->receive('message2', $next);
        $extension->receive('message3', $next);
    }

    /**
     * @dataProvider countProvider
     */
    public function test_receiver_stops_when_maximum_count_exceeded_legacy($max, $shouldStop)
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->atLeastOnce())->method('receive');

        $consumer = $this->createMock(ConsumerInterface::class);
        if (true === $shouldStop) {
            $consumer->expects($this->atLeastOnce())->method('stop');
        } else {
            $consumer->expects($this->never())->method('stop');
        }

        $extension = new MessageCountLimiterReceiver($decorated, $max);
        $extension->receive('message1', $consumer);
        $extension->receive('message2', $consumer);
        $extension->receive('message3', $consumer);
    }

    public function countProvider()
    {
        yield [1, true];
        yield [2, true];
        yield [3, true];
        yield [4, false];
    }

    /**
     *
     */
    public function test_receiver_logs_maximum_count_exceeded_when_logger_is_given()
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->once())->method('receive');
        $next->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with(
                $this->equalTo('Receiver stopped due to maximum count of {count} exceeded'),
                $this->equalTo(['count' => 1])
            );

        $extension = new MessageCountLimiterReceiver(1, $logger);
        $extension->receive('message1', $next);
    }

    /**
     *
     */
    public function test_receiver_logs_maximum_count_exceeded_when_logger_is_given_legacy()
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->once())->method('receive');

        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with(
                $this->equalTo('Receiver stopped due to maximum count of {count} exceeded'),
                $this->equalTo(['count' => 1])
            );

        $extension = new MessageCountLimiterReceiver($decorated, 1, $logger);
        $extension->receive('message1', $consumer);
    }
}
