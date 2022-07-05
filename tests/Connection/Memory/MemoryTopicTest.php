<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;


/**
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Memory
 */
class MemoryTopicTest extends TestCase
{
    /** @var MemoryTopic */
    private $driver;
    /**
     * @var MemoryConnection
     */
    private $connection;

    /**
     * @return MemoryTopic
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection('foo',  new JsonSerializer());

        $this->driver = $this->connection->topic();
    }

    /**
     *
     */
    public function test_empty_publish()
    {
        $message = Message::createForTopic('topic.1', ['content'], 'TestEvent');

        $this->driver->publish($message);

        $this->assertSame(0, $this->driver->awaiting());
    }

    /**
     *
     */
    public function test_publish()
    {
        $this->driver->subscribe(['topic.*'], function() {});
        $this->driver->publish(Message::createForTopic('topic.1', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('topic.2', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('other.2', ['content'], 'TestEvent'));

        $this->assertSame(2, $this->driver->consume(0));
    }

    /**
     *
     */
    public function test_subscribe()
    {
        $this->driver->subscribe(['topic*'], function(TopicEnvelope $envelope) use(&$collector) {
            $collector = $envelope->message();
        });
        $this->driver->publish(Message::createForTopic('topic1', ['content'], 'TestEvent'));
        $this->assertSame(1, $this->driver->consume(0));

        $this->assertSame('TestEvent', $collector->name());
        $this->assertSame('topic1', $collector->queue());
        $this->assertSame(['content'], $collector->data());
    }

    /**
     *
     */
    public function test_subscribe_other_topic()
    {
        $collector = false;

        $this->driver->subscribe(['topic2'], function() use(&$collector) {
            $collector = true;
        });
        $this->driver->publish(Message::createForTopic('topic1', ['content'], 'TestEvent'));
        $this->assertSame(0, $this->driver->consume());

        $this->assertFalse($collector);
    }

    /**
     *
     */
    public function test_subscribe_many_topics()
    {
        $this->driver->subscribe(['other.*', 'topic.*'], function() {});
        $this->driver->publish(Message::createForTopic('topic.1', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('topic.2', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('other.1', ['content'], 'TestEvent'));

        $this->assertSame(3, $this->driver->consume(0));
    }

    /**
     *
     */
    public function test_consume_multiple_messages()
    {
        $this->driver->subscribe(['topic*'], function(TopicEnvelope $envelope) {});
        $this->driver->publish(Message::createForTopic('topic1', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('topic1', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('topic1', ['content'], 'TestEvent'));

        $this->assertSame(3, $this->driver->consume(0));
    }

    /**
     *
     */
//    public function test_publish_error_with_logger()
//    {
//        $serializer = $this->createMock(SerializerInterface::class);
//        $logger = $this->createMock(LoggerInterface::class);
//        $logger->expects($this->once())->method('error')->with('test');
//
//        $this->driver = $this->createPartialMock(MemoryBroadcaster::class, ['doBroadcast']);
//        $this->driver->setSerializer($serializer);
//        $this->driver->setLogger($logger);
//        $this->driver->expects($this->once())->method('doBroadcast')->willThrowException(new \Exception('test'));
//
//        $this->driver->publish(Message::createForTopic(['topic.1', 'topic.2'], ['content'], 'TestEvent'));
//    }

    /**
     *
     */
//    public function test_publish_error_without_logger()
//    {
//        $serializer = $this->createMock(SerializerInterface::class);
//
//        $this->driver = $this->createPartialMock(MemoryBroadcaster::class, ['doBroadcast']);
//        $this->driver->setSerializer($serializer);
//        $this->driver->expects($this->once())->method('doBroadcast')->willThrowException(new \Exception('test'));
//
//        $this->driver->publish(Message::createForTopic(['topic.1', 'topic.2'], ['content'], 'TestEvent'));
//    }

    /**
     *
     */
    public function test_publish_raw()
    {
        $collector = false;

        $this->driver->subscribe(['publish.event'], function(TopicEnvelope $envelope) use(&$collector) {
            $collector = $envelope->message()->data();
        });
        $this->driver->publishRaw('publish.event', '{"event": "Event", "data": "raw"}');
        $this->driver->consume();

        $this->assertEquals('raw', $collector);
    }

    /**
     *
     */
    public function test_inter_consumers_should_only_call_self_subscribers()
    {
        $driver1 = clone $this->driver;
        $driver2 = clone $this->driver;

        $called1 = false;
        $called2 = false;

        $driver1->subscribe(['topic'], function() use(&$called1) { $called1 = true; });
        $driver2->subscribe(['topic'], function() use(&$called2) { $called2 = true; });

        $this->driver->publish(Message::createForTopic('topic', ['content'], 'TestEvent'));

        $driver1->consume(0);

        $this->assertTrue($called1);
        $this->assertFalse($called2);

        $driver2->consume(0);

        $called1 = false;
        $this->assertFalse($called1);
        $this->assertTrue($called2);
    }

    /**
     * @return void
     */
    public function test_count()
    {
        $this->driver->subscribe(['*'], function() {});
        $this->driver->publish(Message::createForTopic('foo.bar', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('foo.baz', ['content'], 'TestEvent'));
        $this->driver->publish(Message::createForTopic('bar.baz', ['content'], 'TestEvent'));

        $this->assertEquals(3, $this->driver->count('*'));
        $this->assertEquals(2, $this->driver->count('foo.*'));
        $this->assertEquals(1, $this->driver->count('foo.bar'));
        $this->assertEquals(0, $this->driver->count('foo.other'));
    }

    /**
     * @return void
     */
    public function test_peek()
    {
        $this->driver->subscribe(['*'], function() {});
        $this->driver->publish(Message::createForTopic('foo.bar', 'content 1', 'TestEvent'));
        $this->driver->publish(Message::createForTopic('foo.baz', 'content 2', 'TestEvent'));
        $this->driver->publish(Message::createForTopic('bar.baz', 'content 3', 'TestEvent'));

        $this->assertCount(3, $this->driver->peek('*'));
        $this->assertCount(2, $this->driver->peek('foo.*'));
        $this->assertCount(1, $this->driver->peek('foo.bar'));
        $this->assertCount(0, $this->driver->peek('foo.other'));

        $this->assertContainsOnly(QueuedMessage::class, $this->driver->peek('*'));
        $this->assertEquals(['content 1', 'content 2', 'content 3'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('*')));
        $this->assertEquals(['content 1', 'content 2'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('foo.*')));
        $this->assertEquals(['content 3'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('bar.*')));

        $this->assertEquals(['content 1', 'content 2'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('*', 2)));
        $this->assertEquals(['content 3'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('*', 2, 2)));
        $this->assertEquals([], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('*', 2, 10)));
    }

    /**
     * @return void
     */
    public function test_peek_should_ignore_multiple_queues()
    {
        $other = $this->connection->topic();
        $this->driver->subscribe(['*'], function() {});
        $other->subscribe(['*'], function () {});

        // Publish on another topic instance
        $other->publish(Message::createForTopic('foo.bar', 'content 1', 'TestEvent'));
        $other->publish(Message::createForTopic('foo.baz', 'content 2', 'TestEvent'));
        $other->publish(Message::createForTopic('bar.baz', 'content 3', 'TestEvent'));

        $this->assertEquals(['content 1', 'content 2', 'content 3'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('*')));
        $this->assertEquals(['content 1', 'content 2'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('foo.*')));
        $this->assertEquals(['content 3'], array_map(function (QueuedMessage $message) { return $message->data(); }, $this->driver->peek('bar.*')));
    }

    /**
     * @return void
     */
    public function test_count_should_ignore_multiple_queues()
    {
        $other = $this->connection->topic();
        $this->driver->subscribe(['*'], function() {});
        $other->subscribe(['*'], function () {});

        // Publish on another topic instance
        $other->publish(Message::createForTopic('foo.bar', 'content 1', 'TestEvent'));
        $other->publish(Message::createForTopic('foo.baz', 'content 2', 'TestEvent'));
        $other->publish(Message::createForTopic('bar.baz', 'content 3', 'TestEvent'));

        $this->assertEquals(3, $this->driver->count('*'));
        $this->assertEquals(2, $this->driver->count('foo.*'));
        $this->assertEquals(1, $this->driver->count('foo.baz'));
    }
}
