<?php

namespace Bdf\Queue;

use Bdf\Queue\Consumer\ConsumerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Worker
 */
class WorkerTest extends TestCase
{
    /** @var ConsumerInterface|MockObject */
    protected $consumer;

    /**
     * 
     */
    protected function setUp(): void
    {
        $this->consumer = $this->createMock(ConsumerInterface::class);
    }

    /**
     *
     */
    public function test_run_consumer_with_default_options()
    {
        $this->consumer->expects($this->once())->method('consume')->with(0);

        $worker = new Worker($this->consumer, false);
        $worker->run();
    }

    /**
     *
     */
    public function test_run_consumer()
    {
        $this->consumer->expects($this->once())->method('consume')->with(10);

        $worker = new Worker($this->consumer, false);
        $worker->run(['duration' => 10]);
    }
}

class TestWorkerStub implements ConsumerInterface {
    /**
     * @inheritDoc
     */
    public function consume(int $duration): void
    {

    }

    /**
     * @inheritDoc
     */
    public function stop(): void
    {

    }
};
