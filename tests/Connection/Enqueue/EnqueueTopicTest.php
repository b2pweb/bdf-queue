<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Serializer\Serializer;
use PHPUnit\Framework\TestCase;

/**
 * Class EnqueueTopicTest
 */
class EnqueueTopicTest extends TestCase
{
    /**
     * @var EnqueueTopic
     */
    private $topic;

    protected function setUp(): void
    {
        $connection = new EnqueueConnection('name', new Serializer());
        $connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->topic = $connection->topic();
    }

    /**
     *
     */
    public function test_functional_publish_subscribe_consume()
    {
        $message = new Message(['foo' => 'bar']);
        $message->setTopic('my-topic');

        $this->topic->subscribe(['my-topic'], function () use(&$parameters) {
            $parameters = func_get_args();
        });

        $this->topic->publish($message);

        $this->assertSame(1, $this->topic->consume(0));

        $this->assertCount(1, $parameters);
        $this->assertInstanceOf(TopicEnvelope::class, $parameters[0]);
        $this->assertSame(['foo' => 'bar'], $parameters[0]->message()->data());
        $this->assertEquals('my-topic', $parameters[0]->message()->queue());
    }

    /**
     *
     */
    public function test_consume_timeout()
    {
        $called = false;

        $this->topic->subscribe(['my-topic'], function () use(&$called) {
            $called = true;
        });

        $this->assertSame(0, $this->topic->consume(0));
        $this->assertFalse($called);
    }
}
