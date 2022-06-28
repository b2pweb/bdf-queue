<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Consumer
 */
class BenchReceiverTest extends TestCase
{
    /**
     * @var BufferingLogger
     */
    private $logger;
    /** @var ReceiverInterface|MockObject */
    protected $extension;
    /** @var ConsumerInterface|MockObject */
    protected $consumer;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->logger = new BufferingLogger();
        $this->extension = $this->createMock(ReceiverInterface::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
    }

    /**
     *
     */
    public function test_reset_without_jobs()
    {
        $this->extension->expects($this->once())->method('receiveTimeout')
            ->with($this->consumer);

        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receiveTimeout($this->consumer);

        $this->assertEmpty($this->logger->cleanLogs());
    }

    /**
     *
     */
    public function test_handle_will_call_next_action()
    {
        $message = new \stdClass();
        $this->extension->expects($this->once())->method('receive')
            ->with($message, $this->consumer);

        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receive($message, $this->consumer);
    }

    /**
     *
     */
    public function test_start()
    {
        $this->extension->expects($this->once())->method('start')->with($this->consumer);

        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->start($this->consumer);
    }

    /**
     *
     */
    public function test_terminate()
    {
        $this->extension->expects($this->once())->method('terminate')->with($this->consumer);

        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->terminate($this->consumer);
    }

    /**
     *
     */
    public function test_reset_with_break_will_generate_report()
    {
        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->terminate($this->consumer);

        $logs = $this->logger->cleanLogs();

        $date = new \DateTime();
        $this->assertCount(1, $logs);
        $this->assertEquals(LogLevel::INFO, $logs[0][0]);
        $this->assertEquals('Benchmark results', $logs[0][1]);
        $this->assertEquals(2, $logs[0][2][0]['Count']);
        $this->assertEquals($date->format('H:i:s'), $logs[0][2]['Time']['Start']);
        $this->assertEquals($date->format('H:i:s'), $logs[0][2]['Time']['End']);
        $this->assertArrayHasKey('Rate', $logs[0][2][0]);
        $this->assertArrayHasKey('Total rate', $logs[0][2][0]);
        $this->assertArrayHasKey('Min', $logs[0][2]['Time']);
        $this->assertArrayHasKey('Max', $logs[0][2]['Time']);
        $this->assertArrayHasKey('Avg', $logs[0][2]['Time']);
        $this->assertArrayHasKey('Total', $logs[0][2]['Time']);
    }

    /**
     *
     */
    public function test_reset_max_job_limit_reached_will_generate_report()
    {
        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->terminate($this->consumer);

        $logs = $this->logger->cleanLogs();

        $this->assertCount(2, $logs);
        $this->assertEquals(5, $logs[0][2][0]['Count']);
    }

    /**
     *
     */
    public function test_reset_will_reset_stats()
    {
        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->terminate($this->consumer);

        $logs = $this->logger->cleanLogs();

        $this->assertCount(4, $logs);
        $this->assertEquals(2, $logs[0][2][0]['Count']);
        $this->assertEquals(1, $logs[1][2][0]['Count']);
    }

    /**
     *
     */
    public function test_report_will_compute_bench_on_unreported_jobs()
    {
        $extension = new BenchReceiver($this->extension, $this->logger, 5);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->terminate($this->consumer);
        $extension->report();

        $logs = $this->logger->cleanLogs();
        $this->assertCount(3, $logs);
        $this->assertEquals(2, $logs[0][2][0]['Count']);
    }

    /**
     *
     */
    public function test_maxHistory()
    {
        $extension = new BenchReceiver($this->extension, $logger = new BufferingLogger(), 1, 2);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->receive(new \stdClass(), $this->consumer);
        $extension->receiveTimeout($this->consumer);
        $extension->terminate($this->consumer);

        $logger->cleanLogs();

        $extension->report();

        $logs = $logger->cleanLogs();
        $this->assertCount(2, $logs);
    }
}
