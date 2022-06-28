<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Connection\Memory\MemoryConnection;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Consumer\Receiver\StopWhenEmptyReceiver;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Testing\StackMessagesReceiver;
use PHPUnit\Framework\TestCase;

/**
 * Class TopicConsumerTest
 */
class TopicConsumerTest extends TestCase
{
    /**
     * @var TopicDriverInterface
     */
    private $driver;

    protected function setUp(): void
    {
        $this->driver = (new MemoryConnection())->topic();
    }

    /**
     *
     */
    public function test_connection()
    {
        $receiver = $this->createMock(ReceiverInterface::class);
        $consumer = new TopicConsumer($this->driver, $receiver, ['my-topic']);

        $this->assertSame($this->driver->connection(), $consumer->connection());
    }

    /**
     *
     */
    public function test_consume_timeout()
    {
        $receiver = $this->createMock(ReceiverInterface::class);

        $consumer = new TopicConsumer($this->driver, new StopWhenEmptyReceiver($receiver), ['my-topic']);

        $receiver->expects($this->never())->method('receive');
        $receiver->expects($this->once())->method('receiveTimeout')->with($consumer);

        $consumer->consume(0);
    }

    /**
     *
     */
    public function test_functional_consume()
    {
        $receiver = new StackMessagesReceiver();

        $consumer = new TopicConsumer($this->driver, new StopWhenEmptyReceiver($receiver), ['my-topic']);
        $consumer->subscribe();

        $this->driver->publish((new Message('foo'))->setTopic('my-topic'));
        $this->driver->publish((new Message('bar'))->setTopic('my-topic'));
        $this->driver->publish((new Message('baz'))->setTopic('other-topic'));

        $consumer->consume(0);

        $this->assertCount(2, $receiver);
        $this->assertEquals('foo', $receiver[0]->message()->data());
        $this->assertEquals('bar', $receiver[1]->message()->data());
    }

    /**
     *
     */
    public function test_functional_consume_multiple_channels()
    {
        $receiver = new StackMessagesReceiver();

        $consumer = new TopicConsumer($this->driver, new StopWhenEmptyReceiver($receiver), ['t1', 't2', 't3']);
        $consumer->subscribe();

        $this->driver->publish((new Message('foo'))->setTopic('t1'));
        $this->driver->publish((new Message('bar'))->setTopic('t3'));

        $consumer->consume(0);

        $this->assertCount(2, $receiver);
        $this->assertEquals('foo', $receiver[0]->message()->data());
        $this->assertEquals('bar', $receiver[1]->message()->data());
    }
}
