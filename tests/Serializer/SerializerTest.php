<?php

namespace Bdf\Queue\Serializer;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Serializer
 */
class SerializerTest extends TestCase
{
    /**
     * @dataProvider serializationProvider
     */
    public function test_serialize_callable($job, $expected)
    {
        $serializer = new Serializer();
        $message = Message::createFromJob($job, '');

        $serialized = $serializer->serialize($message);
        $this->assertStringContainsString($expected, $serialized);

        $unserialized = $serializer->unserialize($serialized, QueuedMessage::class);
        $this->assertInstanceOf(QueuedMessage::class, $unserialized);
        $this->assertEquals($message->toQueue(), $unserialized->toQueue());
    }

    public function test_serialize_unserialize_dateTime()
    {
        $serializer = new Serializer();

        $message = Message::create('data');
        $message->setQueuedAt(new \DateTimeImmutable('2019-02-15 15:58'));

        $serialized = $serializer->serialize($message);
        $unserialized = $serializer->unserialize($serialized);

        $this->assertEquals(new \DateTimeImmutable('2019-02-15 15:58'), $unserialized->queuedAt());
    }
    
    public function serializationProvider()
    {
        return [
            [ [$this, 'test'], 'a:3:{s:3:"job";s:40:"'.__CLASS__.'@test";s:4:"data";s:0:"";s:8:"queuedAt";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"'],
            [ ['idContainer', 'test'], 'a:3:{s:3:"job";s:16:"idContainer@test";s:4:"data";s:0:"";s:8:"queuedAt";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"'],
            [ 'idContainer@test', 'a:3:{s:3:"job";s:16:"idContainer@test";s:4:"data";s:0:"";s:8:"queuedAt";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"'],
        ];
    }
}