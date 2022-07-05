<?php

namespace Bdf\Queue\Destination\Topic;

use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\TopicConsumer;
use Bdf\Queue\Destination\Promise\NullPromise;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\TopicEnvelope;
use Bdf\Queue\Testing\StackMessagesReceiver;
use PHPUnit\Framework\TestCase;

/**
 * Class TopicDestinationTest
 */
class TopicDestinationTest extends TestCase
{
    /**
     * @var MemoryConnection
     */
    protected $connection;

    /**
     * @var TopicDriverInterface
     */
    protected $driver;

    /**
     * @var TopicDestination
     */
    protected $destination;


    /**
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection();
        $this->driver = $this->connection->topic();
        $this->destination = new TopicDestination($this->driver, 'my-topic');
    }

    /**
     *
     */
    public function test_send()
    {
        $this->driver->subscribe(['my-topic'], function ($message) use(&$last) {
            $last = $message;
        });

        $promise = $this->destination->send($message = new Message('foo'));

        $this->assertEquals('my-topic', $message->topic());
        $this->assertInstanceOf(NullPromise::class, $promise);
        $this->assertNull($promise->await());

        $this->driver->consume(0);

        $this->assertInstanceOf(TopicEnvelope::class, $last);
        $this->assertEquals('foo', $last->message()->data());
    }

    /**
     *
     */
    public function test_raw()
    {
        $this->driver->subscribe(['my-topic'], function ($message) use(&$last) {
            $last = $message;
        });

        $this->destination->raw('foo');

        $this->driver->consume(0);

        $this->assertInstanceOf(TopicEnvelope::class, $last);
        $this->assertEquals('foo', $last->message()->raw());
    }

    /**
     *
     */
    public function test_consumer()
    {
        $receiver = new StackMessagesReceiver();

        $consumer = $this->destination->consumer(new MessageCountLimiterReceiver($receiver, 1));
        $consumer->subscribe();

        $this->assertInstanceOf(TopicConsumer::class, $consumer);

        // @todo complete when TopicConsumer became testable
        $this->destination->send(new Message('foo'));

        $consumer->consume(0);

        $this->assertCount(1, $receiver);
        $this->assertEquals('foo', $receiver[0]->message()->data());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_declare_destroy_not_manageable()
    {
        $destination = new TopicDestination((new NullConnection(''))->topic(), 'my-topic');

        $destination->declare();
        $destination->destroy();
    }

    /**
     *
     */
    public function test_declare_destroy()
    {
        $connection = new class('') extends NullConnection implements ManageableTopicInterface {
            public $declared = [];

            public function declareTopic(string $topic): void
            {
                $this->declared[$topic] = true;
            }

            public function deleteTopic(string $topic): void
            {
                unset($this->declared[$topic]);
            }
        };

        $destination = new TopicDestination($connection->topic(), 'my-topic');

        $destination->declare();
        $this->assertSame(['my-topic' => true], $connection->declared);

        $destination->destroy();
        $this->assertSame([], $connection->declared);
    }
}
