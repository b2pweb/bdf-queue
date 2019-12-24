<?php

namespace Bdf\Queue\Connection\Memory;

use Bdf\Queue\Message\Message;
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
     * @return MemoryTopic
     */
    protected function setUp(): void
    {
        $connection = new MemoryConnection('foo',  new JsonSerializer());

        $this->driver = $connection->topic();
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
}
