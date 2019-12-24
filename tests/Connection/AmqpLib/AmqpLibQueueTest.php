<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Serializer\JsonSerializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Amqp
 */
class AmqpLibQueueTest extends TestCase
{
    /** @var AmqpLibConnection */
    private $driver;
    /** @var AbstractConnection|MockObject */
    private $connection;
    /** @var AMQPChannel|MockObject */
    private $channel;

    /**
     * 
     */
    public function setUp(): void
    {
        $this->channel = $this->createMock(AMQPChannel::class);
        $this->connection = $this->createMock(AbstractConnection::class);

        $this->driver = new AmqpLibConnection('foo', new JsonSerializer());
        $this->driver->setConfig([]);
        $this->driver->setAmqpConnection($this->connection);
        $this->driver->setAmqpChannel($this->channel);
    }

    /**
     *
     */
    public function test_push()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');

        $amqpMessage = new AMQPMessage(json_encode($message->toQueue()), [
            'Content-Type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->expects($this->never())->method('queue_declare');
        $this->channel->expects($this->once())->method('basic_publish')
            ->with($this->callback(function(AMQPMessage $amqpMessageCandidate) use($message, $amqpMessage) {
                $candidate = json_decode($amqpMessageCandidate->body, true);
                $this->assertRegExp('/[0-9\-\:\. ]+/', $candidate['queuedAt']['date']);

                $expected = $message->toQueue();
                unset($candidate['queuedAt']);
                unset($expected['queuedAt']);

                $this->assertEquals($expected, $candidate);
                $this->assertEquals($amqpMessage->get_properties(), $amqpMessageCandidate->get_properties());
                return true;
            }), '', 'queue', false, false);

        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_push_auto_declare()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');

        $this->channel->expects($this->once())->method('queue_declare');
        $this->channel->expects($this->once())->method('basic_publish');

        $this->driver->setConfig(['auto_declare' => true]);
        $this->driver->queue()->push($message);
    }

    /**
     *
     */
    public function test_push_option()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');
        $message->addHeader('flags', AmqpLibConnection::FLAG_MESSAGE_IMMEDIATE | AmqpLibConnection::FLAG_MESSAGE_MANDATORY);

        $amqpMessage = new AMQPMessage(json_encode($message->toQueue()), [
            'Content-Type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->expects($this->once())->method('queue_declare')->with('queue');
        $this->channel->expects($this->once())->method('basic_publish')
            ->with($this->callback(function(AMQPMessage $amqpMessageCandidate) use($message, $amqpMessage) {
                $candidate = json_decode($amqpMessageCandidate->body, true);
                $this->assertRegExp('/[0-9\-\:\. ]+/', $candidate['queuedAt']['date']);

                $expected = $message->toQueue();
                unset($candidate['queuedAt']);
                unset($expected['queuedAt']);

                $this->assertEquals($expected, $candidate);
                $this->assertEquals($amqpMessage->get_properties(), $amqpMessageCandidate->get_properties());
                return true;
            }), '', 'queue', true, true);

        $this->driver->setConfig(['auto_declare' => true]);
        $this->driver->queue()->push($message);
    }

    /**
     * 
     */
    public function test_push_raw()
    {
        $message = new AMQPMessage('test', [
            'Content-Type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->expects($this->once())->method('queue_declare')->with('queue');
        $this->channel->expects($this->once())->method('basic_publish')->with($message, '', 'queue');

        $this->driver->setConfig(['auto_declare' => true]);
        $this->driver->queue()->pushRaw('test', 'queue');
    }

    /**
     *
     */
    public function test_push_with_delay()
    {
        $message = new AMQPMessage('test', [
            'Content-Type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->expects($this->at(0))->method('queue_declare')->with('queue_deferred_1')->willReturn(['queue_deferred_1']);
        $this->channel->expects($this->once())->method('basic_publish')->with($message, '', 'queue_deferred_1');

        $this->driver->queue()->pushRaw('test', 'queue', 1);
    }

    /**
     *
     */
    public function test_pop()
    {
        $amqpMessage = $this->createMock(AMQPMessage::class);
        $amqpMessage->body = '{"data":"foo"}';

        $this->channel->expects($this->never())->method('queue_declare')->with('queue');
        $this->channel->expects($this->once())->method('basic_get')->with('queue', false)->willReturn($amqpMessage);

        $message = $this->driver->queue()->pop('queue', 1)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame($amqpMessage, $message->internalJob());
    }

    /**
     *
     */
    public function test_pop_auto_declare()
    {
        $amqpMessage = $this->createMock(AMQPMessage::class);
        $amqpMessage->body = '{"data":"foo"}';

        $this->channel->expects($this->once())->method('queue_declare')->with('queue');
        $this->channel->expects($this->once())->method('basic_get')->with('queue', false)->willReturn($amqpMessage);

        $this->driver->setConfig(['auto_declare' => true]);
        $message = $this->driver->queue()->pop('queue', 1)->message();

        $this->assertInstanceOf(QueuedMessage::class, $message);
        $this->assertSame('foo', $message->data());
        $this->assertSame('{"data":"foo"}', $message->raw());
        $this->assertSame('queue', $message->queue());
        $this->assertSame($amqpMessage, $message->internalJob());
    }

    /**
     *
     */
    public function test_pop_flags_options()
    {
        $amqpMessage = $this->createMock(AMQPMessage::class);
        $amqpMessage->body = '{"data":"foo"}';

        $this->channel->expects($this->once())->method('basic_get')
            ->with('queue', true)->willReturn($amqpMessage);

        $this->driver->setConfig([
            'consumer_flags' => AmqpLibConnection::FLAG_CONSUMER_NOACK
        ]);

        $this->driver->queue()->pop('queue', 1);
    }

    /**
     *
     */
    public function test_pop_end_of_queue()
    {
        $this->channel->expects($this->any())->method('basic_get')->willReturn(null);

        $this->assertSame(null, $this->driver->queue()->pop('queue', 0));
    }

    /**
     *
     */
    public function test_acknowledge()
    {
        $message = new QueuedMessage();
        $message->setInternalJob($this->createMock(AMQPMessage::class));
        $message->setQueue('queue');
        $message->internalJob()->delivery_info['delivery_tag'] = 1;

        $this->channel->expects($this->once())->method('basic_ack')->with(1);

        $this->driver->queue()->acknowledge($message);
    }

    /**
     *
     */
    public function test_release()
    {
        $message = new QueuedMessage();
        $message->setInternalJob($this->createMock(AMQPMessage::class));
        $message->setQueue('queue');
        $message->internalJob()->delivery_info['delivery_tag'] = 1;

        $this->channel->expects($this->once())->method('basic_nack')->with(1);

        $this->driver->queue()->release($message);
    }

    /**
     *
     */
    public function test_count_unsuported()
    {
        $this->assertSame(null, $this->driver->queue()->count('queue'));
    }

    /**
     *
     */
    public function test_stats_unsuported()
    {
        $stats = [];

        $this->assertSame($stats, $this->driver->queue()->stats());
    }
}
