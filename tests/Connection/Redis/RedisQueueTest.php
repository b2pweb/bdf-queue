<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_PhpRedis
 */
class RedisQueueTest extends TestCase
{
    /**
     * @var RedisConnection
     */
    private $driver;
    /**
     * @var RedisInterface|MockObject
     */
    private $redis;
    /**

    /**
     * 
     */
    public function setUp(): void
    {
        $this->redis = $this->createMock(RedisInterface::class);

        $this->driver = new RedisConnection('foo', new JsonSerializer());
        $this->driver->setRedis($this->redis);
        $this->driver->setConfig([]);
    }

    /**
     *
     */
    public function test_push()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');

        $this->redis->expects($this->never())->method('sAdd')->with(RedisConnection::QUEUE_KEY, 'queue');
        $this->redis->expects($this->once())->method('rPush')->with('queues:queue', $this->stringContains('{"job":"test","data":"foo","queuedAt":{"date":'));

        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_push_auto_declare()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');

        $this->redis->expects($this->once())->method('sAdd')->with(RedisConnection::QUEUE_KEY, 'queue');
        $this->redis->expects($this->once())->method('rPush')->with('queues:queue', $this->stringContains('{"job":"test","data":"foo","queuedAt":{"date":'));

        $this->driver->setConfig(['auto_declare' => true]);
        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_push_raw()
    {
        $this->redis->expects($this->once())->method('rPush')->with('queues:queue', 'test');

        $this->driver->queue()->pushRaw('test', 'queue');
    }

    /**
     *
     */
    public function test_push_with_delay()
    {
        $message = Message::createFromJob('test', 'foo', 'queue', 1);

        $this->redis->expects($this->once())->method('zAdd')->with('queues:queue:delayed');

        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_pop()
    {
        $this->redis->expects($this->never())->method('sAdd')->with(RedisConnection::QUEUE_KEY, 'queue');
        $this->redis->expects($this->once())->method('blPop')->with(['queues:queue'], 1)->willReturn([1 => '{"data":"foo"}']);

        $message = $this->driver->queue()->pop('queue', 1)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame(null, $message->internalJob());
    }

    /**
     *
     */
    public function test_pop_auto_declare()
    {
        $this->redis->expects($this->once())->method('sAdd')->with(RedisConnection::QUEUE_KEY, 'queue');
        $this->redis->expects($this->once())->method('blPop')->with(['queues:queue'], 1)->willReturn([1 => '{"data":"foo"}']);

        $this->driver->setConfig(['auto_declare' => true]);
        $message = $this->driver->queue()->pop('queue', 1)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame(null, $message->internalJob());
    }

    /**
     *
     */
    public function test_pop_end_of_queue()
    {
        $this->redis->expects($this->once())->method('blPop')->with(['queues:queue'], 1)->willReturn(null);

        $this->assertSame(null, $this->driver->queue()->pop('queue', 1));
    }

    /**
     *
     */
    public function test_acknowledge_not_supported()
    {
        $message = new QueuedMessage();
        $message->setQueue('queue');

        $this->assertNull($this->driver->queue()->acknowledge($message));
    }

    /**
     *
     */
    public function test_release()
    {
        $this->redis->expects($this->once())->method('rPush')->with('queues:queue', $this->stringContains('{"job":"test","data":"foo","queuedAt":{"date":'));

        $this->driver->queue()->release(QueuedMessage::createFromJob('test', 'foo', 'queue'));
    }

    /**
     *
     */
    public function test_count()
    {
        $this->redis->expects($this->once())->method('lLen')->with('queues:queue')->willReturn(1);

        $this->assertSame(1, $this->driver->queue()->count('queue'));
    }

    /**
     *
     */
    public function test_stats()
    {
        $stats = [
            'queues'  => [
                [
                    'queue' => 'queue',
                    'jobs in queue' => 1,
                    'delayed' => 2,
                ],
            ],
        ];

        $this->redis->expects($this->once())->method('sMembers')->willReturn(['queue']);
        $this->redis->expects($this->once())->method('lLen')->with('queues:queue')->willReturn(1);
        $this->redis->expects($this->once())->method('zCard')->with('queues:queue:delayed')->willReturn(2);

        $this->assertSame($stats, $this->driver->queue()->stats());
    }
}
