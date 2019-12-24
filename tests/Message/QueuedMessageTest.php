<?php

namespace Bdf\Queue\Message;

use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Message
 */
class QueuedMessageTest extends TestCase
{
    /**
     * 
     */
    public function test_default_value()
    {
        $message = new QueuedMessage();

        $this->assertNull($message->raw());
        $this->assertNull($message->internalJob());
        $this->assertSame(1, $message->attempts());
    }

    /**
     *
     */
    public function test_accessors()
    {
        $message = new QueuedMessage();

        $message->setRaw('{}');
        $this->assertSame('{}', $message->raw());

        $message->setInternalJob([]);
        $this->assertSame([], $message->internalJob());

        $this->assertSame(1, $message->attempts());
        $message->incrementAttempts();
        $this->assertSame(2, $message->attempts());
        $message->resetAttempts();
        $this->assertSame(1, $message->attempts());
        $message->setAttempts(6);
        $this->assertSame(6, $message->attempts());
    }

    /**
     *
     */
    public function test_to_queue()
    {
        $message = QueuedMessage::createFromJob('job', 'data');
        $message->setAttempts(2);

        $queued = $message->toQueue();

        $this->assertCount(4, $queued);
        $this->assertSame('job', $queued['job']);
        $this->assertSame('data', $queued['data']);
        $this->assertSame(2, $queued['attempts']);
        $this->assertNotEmpty($queued['queuedAt']);
    }

    /**
     *
     */
    public function test_from_queue()
    {
        $now = new \DateTimeImmutable();

        $message = QueuedMessage::createFromJob('job', 'data');
        $message->setAttempts(2);
        $message->setQueuedAt($now);

        $data = [
            'job' => 'job',
            'data' => 'data',
            'attempts' => 2,
            'queuedAt' => $now,
        ];

        $this->assertEquals($message, QueuedMessage::fromQueue($data));
    }
}
