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
class MemoryLimiterReceiverTest extends TestCase
{
    /**
     * @dataProvider memoryProvider
     */
    public function test_receiver_stops_when_memory_limit_exceeded(string $method, int $memoryUsage, int $memoryLimit, bool $shouldStop)
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->once())->method($method);

        if (true === $shouldStop) {
            $next->expects($this->once())->method('stop');
        } else {
            $next->expects($this->never())->method('stop');
        }

        $memoryResolver = function () use ($memoryUsage) {
            return $memoryUsage;
        };

        $extension = new MemoryLimiterReceiver($memoryLimit, null, $memoryResolver);
        if ($method === 'receiveTimeout') {
            $extension->receiveTimeout($next);
        } else {
            $extension->receive('message', $next);
        }
    }

    /**
     * @dataProvider memoryProvider
     */
    public function test_receiver_stops_when_memory_limit_exceeded_legacy(string $method, int $memoryUsage, int $memoryLimit, bool $shouldStop)
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->once())->method($method);

        $consumer = $this->createMock(ConsumerInterface::class);
        if (true === $shouldStop) {
            $consumer->expects($this->once())->method('stop');
        } else {
            $consumer->expects($this->never())->method('stop');
        }

        $memoryResolver = function () use ($memoryUsage) {
            return $memoryUsage;
        };

        $extension = new MemoryLimiterReceiver($decorated, $memoryLimit, null, $memoryResolver);
        if ($method === 'receiveTimeout') {
            $extension->receiveTimeout($consumer);
        } else {
            $extension->receive('message', $consumer);
        }
    }

    public function memoryProvider()
    {
        yield ['receive', 2048, 1024, true];
        yield ['receiveTimeout', 2048, 1024, true];
        yield ['receive', 1024, 1024, false];
        yield ['receiveTimeout', 1024, 1024, false];
        yield ['receive', 1024, 2048, false];
        yield ['receiveTimeout', 1024, 2048, false];
    }

    /**
     *
     */
    public function test_receiver_logs_memory_exceeded_when_logger_is_given()
    {
        $next = $this->createMock(NextInterface::class);
        $next->expects($this->once())->method('receive');
        $next->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to memory limit of {limit} exceeded', ['limit' => 64 * 1024 * 1024]);

        $memoryResolver = function () {
            return 70 * 1024 * 1024;
        };

        $extension = new MemoryLimiterReceiver(64 * 1024 * 1024, $logger, $memoryResolver);
        $extension->receive('message', $next);
    }

    /**
     *
     */
    public function test_receiver_logs_memory_exceeded_when_logger_is_given_legacy()
    {
        $decorated = $this->createMock(ReceiverInterface::class);
        $decorated->expects($this->once())->method('receive');

        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to memory limit of {limit} exceeded', ['limit' => 64 * 1024 * 1024]);

        $memoryResolver = function () {
            return 70 * 1024 * 1024;
        };

        $extension = new MemoryLimiterReceiver($decorated, 64 * 1024 * 1024, $logger, $memoryResolver);
        $extension->receive('message', $consumer);
    }
}
