<?php

namespace Bdf\Queue\Destination\Queue;

use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class QueuePromiseTest extends TestCase
{
    /**
     * 
     */
    public function test_receive_no_job()
    {
        $connection = $this->createMock(QueueDriverInterface::class);
        $connection->expects($this->once())->method('pop')->with('queue', 0)->willReturn(null);

        $promise = new QueuePromise($connection, 'queue', 'id');
        
        $this->assertNull($promise->await());
    }

    /**
     * 
     */
    public function test_receive_job()
    {
        $message = QueuedMessage::createFromJob('job', 'data');
        $message->addHeader('correlationId', 'id');

        $connection = $this->createMock(QueueDriverInterface::class);
        $envelope = new QueueEnvelope($connection, $message);

        $connection->expects($this->once())->method('pop')->with('queue', 0)->willReturn($envelope);

        $promise = new QueuePromise($connection, 'queue', 'id');
        
        $this->assertSame($message, $promise->await());
        $this->assertTrue($envelope->isDeleted());
        $this->assertFalse($envelope->isRejected());

        // received once
        $this->assertSame($message, $promise->await());
    }

    /**
     * 
     */
    public function test_receive__wrong_job()
    {
        $message = QueuedMessage::createFromJob('job', 'data');
        $message->addHeader('correlationId', 'not_same_id');
        
        $connection = $this->createMock(QueueDriverInterface::class);
        $envelope = new QueueEnvelope($connection, $message);

        $connection->expects($this->once())->method('pop')->with('queue', 0)->willReturn($envelope);

        $promise = new QueuePromise($connection, 'queue', 'id');
        
        $this->assertNull($promise->await());
        $this->assertTrue($envelope->isDeleted());
        $this->assertTrue($envelope->isRejected());
    }

    /**
     *
     */
    public function test_prepareMessage()
    {
        $message = new Message();
        $message->setQueue('queue');

        $this->assertSame($message, QueuePromise::prepareMessage($message));
        $this->assertSame('queue_reply', $message->header('replyTo'));
        $this->assertEquals(16, strlen($message->header('correlationId')));
    }

    /**
     *
     */
    public function test_fromMessage()
    {
        $message = new Message();
        $message->addHeader('replyTo', 'reply_queue');
        $message->addHeader('correlationId', '123');

        $driver = $this->createMock(QueueDriverInterface::class);

        $this->assertEquals(
            new QueuePromise($driver, 'reply_queue', '123'),
            QueuePromise::fromMessage($driver, $message)
        );
    }
}
