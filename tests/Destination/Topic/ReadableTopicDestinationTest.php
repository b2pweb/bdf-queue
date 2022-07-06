<?php

namespace Bdf\Queue\Destination\Topic;

use Bdf\Queue\Connection\Gearman\GearmanConnection;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Serializer\Serializer;

require_once __DIR__ . '/TopicDestinationTest.php';

/**
 * Class TopicDestinationTest
 *
 * @property ReadableTopicDestination $destination
 */
class ReadableTopicDestinationTest extends TopicDestinationTest
{
    /**
     *
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->destination = new ReadableTopicDestination($this->driver, 'my-topic');
    }

    public function test_peek()
    {
        $this->driver->subscribe(['*'], function () {});

        $this->destination->send(new Message('foo'));
        $this->destination->send(new Message('bar'));

        $this->assertEquals(['foo', 'bar'], array_map(function ($message) { return $message->data(); }, $this->destination->peek()));
    }

    public function test_peek_not_supported()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('works only with peekable connection.');

        $connection = new GearmanConnection('test', new Serializer());
        $connection->setConfig([]);

        $destination = new ReadableTopicDestination($connection->topic(), 'test');
        $destination->peek();
    }

    public function test_count_not_supported()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('works only with countable connection.');

        $connection = new GearmanConnection('test', new Serializer());
        $connection->setConfig([]);

        $destination = new ReadableTopicDestination($connection->topic(), 'test');
        $destination->count();
    }

    public function test_count()
    {
        $this->driver->subscribe(['*'], function () {});

        $this->destination->send(new Message('foo'));
        $this->destination->send(new Message('bar'));

        $this->assertEquals(2, $this->destination->count());
    }
}
