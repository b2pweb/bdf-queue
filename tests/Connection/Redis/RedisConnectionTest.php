<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_PhpRedis
 */
class RedisConnectionTest extends TestCase
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
    public function test_set_config()
    {
        $config = [
            'host'      => '127.0.0.1',
            'port'      => 6379,
            'timeout'   => 0,
            'prefix'    => RedisConnection::PREFIX,
            'auto_declare' => false,
            'vendor' => 'phpredis',
        ];

        $this->assertSame($config, $this->driver->config());
        $this->assertSame(false, $this->driver->shouldAutoDeclare());
        $this->assertSame(RedisConnection::PREFIX, $this->driver->queuePrefix());
    }

    /**
     *
     */
    public function test_declare_queue()
    {
        $this->redis->expects($this->once())->method('sAdd')->with(RedisConnection::QUEUE_KEY, 'queue');

        $this->driver->declareQueue('queue');
    }

    /**
     *
     */
    public function test_delete_queue()
    {
        $this->redis->expects($this->once())->method('sRem')->with(RedisConnection::QUEUE_KEY, 'queue');
        $this->redis->expects($this->at(1))->method('del')->with('queues:queue');
        $this->redis->expects($this->at(2))->method('del')->with('queues:queue:delayed');

        $this->driver->deleteQueue('queue');
    }

    /**
     *
     */
    public function test_close()
    {
        $this->redis->expects($this->once())->method('close');

        $this->driver->close();
        // close once
        $this->driver->close();
    }

    /**
     *
     */
    public function test_queue()
    {
        $this->assertInstanceOf(RedisQueue::class, $this->driver->queue());
    }

    /**
     *
     */
    public function test_topic()
    {
        $this->assertInstanceOf(RedisTopic::class, $this->driver->topic());
    }
}
