<?php

namespace Bdf\Queue\Connection\Pheanstalk;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Pheanstalk
 */
class PheanstalkQueueTest extends TestCase
{
    /**
     * @var PheanstalkQueue
     */
    private $driver;
    /**
     * @var PheanstalkInterface|MockObject
     */
    private $pheanstalk;
    /**

    /**
     * 
     */
    public function setUp(): void
    {
        $this->pheanstalk = $this->createMock(PheanstalkInterface::class);

        $connection = new PheanstalkConnection('foo', new JsonSerializer());
        $connection->setConfig([]);
        $connection->setPheanstalk($this->pheanstalk);
        $this->driver = $connection->queue();
    }

    /**
     * 
     */
    public function test_push()
    {
        $message = Message::createFromJob('test', 'foo', 'queue', 1);
        $message->addHeader('priority', 2);
        $message->addHeader('ttr', 3);

        $regex = preg_quote('{"job":"test","data":"foo","queuedAt":{"date":"').
            '[0-9- \.\:]+'.
            preg_quote('","timezone_type":').
            '[0-9]+'.
            preg_quote(',"timezone":"').
            '[\w\/\\\]+'.
            preg_quote('"},"headers":{"priority":2,"ttr":3}}');

        $this->pheanstalk->expects($this->once())->method('useTube')->with('queue')->willReturnSelf();
        $this->pheanstalk->expects($this->once())->method('put')
            ->with(
                $this->matchesRegularExpression("#^$regex$#")
                , 2, 1, 3
            );

        $this->driver->push($message);
    }

    /**
     *
     */
    public function test_push_raw()
    {
        $this->pheanstalk->expects($this->once())->method('useTube')->with('queue')->willReturnSelf();
        $this->pheanstalk->expects($this->once())->method('put')->with('test', Pheanstalk::DEFAULT_PRIORITY, 1);

        $this->driver->pushRaw('test', 'queue', 1);
    }

    /**
     *
     */
    public function test_pop()
    {
        $job = $this->createMock(PheanstalkJob::class);
        $job->expects($this->once())->method('getData')->willReturn('{"data":"foo"}');

        $this->pheanstalk->expects($this->once())->method('watchOnly')->with('queue')->willReturnSelf();
        $this->pheanstalk->expects($this->once())->method('reserve')->with(1)->willReturn($job);

        $message = $this->driver->pop('queue', 1)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame($job, $message->internalJob());
    }

    /**
     *
     */
    public function test_pop_end_of_queue()
    {
        $this->pheanstalk->expects($this->once())->method('watchOnly')->willReturnSelf();
        $this->pheanstalk->expects($this->once())->method('reserve')->willReturn(null);

        $this->assertSame(null, $this->driver->pop('queue', 1));
    }

    /**
     *
     */
    public function test_acknowledge()
    {
        $message = new QueuedMessage();
        $message->setInternalJob($this->createMock(PheanstalkJob::class));
        $message->setQueue('queue');

        $this->pheanstalk->expects($this->once())->method('delete')->with($message->internalJob());

        $this->driver->acknowledge($message);
    }

    /**
     *
     */
    public function test_release()
    {
        $message = new QueuedMessage();
        $message->setInternalJob($this->createMock(PheanstalkJob::class));
        $message->setQueue('queue');

        $this->pheanstalk->expects($this->once())->method('release')->with($message->internalJob());

        $this->driver->release($message);
    }

    /**
     *
     */
    public function test_count()
    {
        $this->pheanstalk->expects($this->once())->method('statsTube')->with('queue')->willReturn(['current-jobs-ready' => 1]);

        $this->assertSame(1, $this->driver->count('queue'));
    }
}
