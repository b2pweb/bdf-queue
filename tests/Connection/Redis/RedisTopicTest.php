<?php

namespace Bdf\Queue\Connection\Redis;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class RedisTopicTest
 */
class RedisTopicTest extends TestCase
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
    public function test_publish()
    {
        $topic = $this->driver->topic();

        $this->redis->expects($this->once())
            ->method('publish')
            ->with('my-topic', $this->callback(function($json) {
                $data = json_decode($json, true);

                return array_keys($data) === ['data', 'queuedAt', 'headers'] && $data['data'] === 'foo';
            }))
        ;

        $topic->publish((new Message('foo'))->setTopic('my-topic'));
    }

    /**
     *
     */
    public function test_publishRaw()
    {
        $topic = $this->driver->topic();

        $this->redis->expects($this->once())
            ->method('publish')
            ->with('my-topic', 'foo')
        ;

        $topic->publishRaw('my-topic', 'foo');
    }

    /**
     *
     */
    public function test_consume()
    {
        $topic = $this->driver->topic();

        $this->redis->expects($this->once())->method('setReadTimeout')->with(5);
        $this->redis->expects($this->once())->method('psubscribe')->with(['t1', 't2', 't3'], [$topic, 'callSubscriber'])->willReturn(3);

        $topic->subscribe(['t1', 't2'], function () {});
        $topic->subscribe(['t3'], function () {});

        $this->assertEquals(3, $topic->consume(5));
    }

    /**
     *
     */
    public function test_subscribe_with_pattern()
    {
        $topic = $this->driver->topic();

        $this->redis->expects($this->once())->method('psubscribe')
            ->with(['pattern.*', 'pattern.?'])->willReturn(3);

        $topic->subscribe(['pattern.*', 'pattern.?'], function () {});

        $topic->consume(0);
    }

    /**
     *
     */
    public function test_functional()
    {
        $redis = new FakeRedisPubSub();

        $driver = new RedisConnection('foo', new JsonSerializer());
        $driver->setRedis($redis);
        $driver->setConfig([]);

        $topic = $driver->topic();

        $topic->subscribe(['t1', 't2'], function ($message) use(&$last) {
            $last = $message;
        });

        $this->assertEquals(0, $topic->consume(0));
        $this->assertNull($last);

        $topic->publish((new Message('foo'))->setTopic('t1'));
        $this->assertEquals(1, $topic->consume(0));
        $this->assertInstanceOf(TopicEnvelope::class, $last);
        $this->assertSame($driver, $last->connection());
        $this->assertEquals('foo', $last->message()->data());

        $topic->publish((new Message('foo'))->setTopic('t1'));
        $topic->publish((new Message('bar'))->setTopic('t2'));
        $topic->publish((new Message('baz'))->setTopic('t2'));
        $this->assertEquals(3, $topic->consume(0));
    }
}

class FakeRedisPubSub implements RedisInterface
{
    public $pending = [];

    public function close() { }
    public function sAdd($key, $value) { }
    public function sRem($key, $member) { }
    public function del($key) { }
    public function zAdd($key, $score, $value) { }
    public function rPush($key, $value) { }
    public function blPop(array $keys, $timeout) { }
    public function evaluate($script, array $keys = [], array $args = []) { }
    public function lLen($key) { }
    public function zCard($key) { }
    public function sMembers($key) { }
    public function setReadTimeout($timeout) { }

    public function publish($channel, $message)
    {
        $this->pending[$channel][] = $message;
    }

    public function psubscribe($patterns, $callback)
    {
        $count = 0;

        foreach ($patterns as $channel) {
            foreach ($this->pending[$channel] ?? [] as $message) {
                $callback($channel, $channel, $message);
                ++$count;
            }
        }

        $this->pending = [];

        return $count;
    }
}