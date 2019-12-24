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
