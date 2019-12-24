<?php

namespace Bdf\Queue\Destination\Topic;

use Bdf\Queue\Connection\ManageableTopicInterface;
use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\Null\NullConnection;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\Receiver\MessageCountLimiterReceiver;
use Bdf\Queue\Consumer\TopicConsumer;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Testing\StackMessagesReceiver;
use PHPUnit\Framework\TestCase;

/**
 * Class MultiTopicDestinationTest
 */
class MultiTopicDestinationTest extends TestCase
{
    /**
     * @var MemoryConnection
     */
    private $connection;

    /**
     * @var TopicDriverInterface
     */
    private $driver;

    /**
     * @var TopicDestination
     */
    private $destination;


    /**
     *
     */
    protected function setUp(): void
    {
        $this->connection = new MemoryConnection();
        $this->driver = $this->connection->topic();
        $this->destination = new MultiTopicDestination($this->driver, ['t1', 't2']);
    }

    /**
     *
     */
    public function test_send()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->destination->send(new Message('foo'));
    }

    /**
     *
     */
    public function test_raw()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->destination->raw('foo');
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

        $this->driver->publish((new Message('foo'))->setTopic('t1'));
        $this->driver->publish((new Message('bar'))->setTopic('t2'));

        $consumer->consume(0);

        $this->assertCount(2, $receiver);
        $this->assertEquals('foo', $receiver[0]->message()->data());
        $this->assertEquals('t1', $receiver[0]->message()->queue());
        $this->assertEquals('bar', $receiver[1]->message()->data());
        $this->assertEquals('t2', $receiver[1]->message()->queue());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_declare_destroy_not_manageable()
    {
        $destination = new MultiTopicDestination((new NullConnection(''))->topic(), ['t1', 't2']);

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

        $destination = new MultiTopicDestination($connection->topic(), ['t1', 't2']);

        $destination->declare();
        $this->assertSame(['t1' => true, 't2' => true], $connection->declared);

        $destination->destroy();
        $this->assertSame([], $connection->declared);
    }
}
