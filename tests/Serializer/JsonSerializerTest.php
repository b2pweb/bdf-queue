<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Serializer
 */
class JsonSerializerTest extends TestCase
{
    /**
     * @dataProvider serializationProvider
     */
    public function test_serialize_callable($job, $expected)
    {
        $serializer = new JsonSerializer();
        $message = Message::createFromJob($job, '');

        $serialized = $serializer->serialize($message);
        $this->assertStringContainsString($expected, $serialized);

        $unserialized = $serializer->unserialize($serialized, QueuedMessage::class);
        $this->assertInstanceOf(QueuedMessage::class, $unserialized);
        $this->assertEquals($message->toQueue(), $unserialized->toQueue());
    }

    public function test_serialize_unserialize_dateTime()
    {
        $serializer = new JsonSerializer();

        $message = Message::create('data');
        $message->setQueuedAt(new \DateTimeImmutable('2019-02-15 15:58'));

        $serialized = $serializer->serialize($message);
        $unserialized = $serializer->unserialize($serialized);

        $this->assertEquals(new \DateTimeImmutable('2019-02-15 15:58'), $unserialized->queuedAt());
    }
    
    public function serializationProvider()
    {
        return [
            [ [$this, 'test'], '{"job":"Bdf\\\Queue\\\Serializer\\\JsonSerializerTest@test","data":"","queuedAt":{"date":'],
            [ ['idContainer', 'test'], '{"job":"idContainer@test","data":"","queuedAt":{"date":'],
            [ 'idContainer@test', '{"job":"idContainer@test","data":"","queuedAt":{"date":'],
        ];
    }
}