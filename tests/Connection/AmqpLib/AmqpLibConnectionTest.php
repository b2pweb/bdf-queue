<?php

namespace Bdf\Queue\Connection\AmqpLib;

use Bdf\Queue\Serializer\JsonSerializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Connection
 * @group Bdf_Queue_Connection_Amqp
 */
class AmqpLibConnectionTest extends TestCase
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
        $this->driver->setAmqpConnection($this->connection);
        $this->driver->setAmqpChannel($this->channel);
    }

    /**
     *
     */
    public function test_setters_getters()
    {
        $this->driver->setConfig([]);

        $expected = [
            'host'     => '127.0.0.1',
            'port'     => 5672,
            'vhost'    => '/',
            'user'     => 'guest',
            'password' => 'guest',
            'sleep_duration' => 200000,
            'queue_flags' => AmqpLibConnection::FLAG_QUEUE_DURABLE,
            'topic_flags' => AmqpLibConnection::FLAG_NOPARAM,
            'consumer_flags' => AmqpLibConnection::FLAG_NOPARAM,
            'qos_prefetch_size' => 0,
            'qos_prefetch_count' => 1,
            'qos_global' => false,
            'auto_declare' => false,
            'group' => 'bdf',
        ];
        $this->assertEquals($expected, $this->driver->config());
        $this->assertSame($this->channel, $this->driver->channel());
        $this->assertSame($this->connection, $this->driver->connection());
    }

    /**
     *
     */
    public function test_declare_queue()
    {
        $this->channel->expects($this->once())->method('queue_declare')->with('queue', false, true, false, false, false);

        $this->driver->setConfig([]);
        $this->driver->declareQueue('queue');
    }

    /**
     *
     */
    public function test_queue_declaration_options()
    {
        $this->channel->expects($this->once())->method('queue_declare')->with('queue', true, false, true, true, true);

        $this->driver->setConfig([
            'queue_flags' => AmqpLibConnection::FLAG_QUEUE_AUTODELETE | AmqpLibConnection::FLAG_QUEUE_EXCLUSIVE
                | AmqpLibConnection::FLAG_QUEUE_NOWAIT | AmqpLibConnection::FLAG_QUEUE_PASSIVE
        ]);
        $this->driver->declareQueue('queue');
    }

    /**
     *
     */
    public function test_delete_queue()
    {
        $this->channel->expects($this->at(0))->method('queue_delete')->with('queue', false, false, false);

        $this->driver->setConfig([]);
        $this->driver->deleteQueue('queue');
    }

    /**
     *
     */
    public function test_delete_queue_with_option()
    {
        $this->channel->expects($this->once())->method('queue_delete')->with('queue', true, true, true);

        $this->driver->setConfig([
            'queue_flags' => AmqpLibConnection::FLAG_QUEUE_IFUNUSED | AmqpLibConnection::FLAG_QUEUE_IFEMPTY
                | AmqpLibConnection::FLAG_QUEUE_NOWAIT
        ]);
        $this->driver->deleteQueue('queue');
    }

    /**
     *
     */
    public function test_bind()
    {
        $this->channel->expects($this->once())->method('queue_declare')->with('bdf/topic', false, true, false, false, false);
        $this->channel->expects($this->once())->method('queue_bind')->with('bdf/topic', 'topic', '*');

        $this->driver->setConfig([]);
        $this->driver->bind('topic', ['*']);
    }

    /**
     *
     */
    public function test_unbind()
    {
        $this->channel->expects($this->once())->method('queue_unbind')->with('bdf/topic', 'topic', '*');

        $this->driver->setConfig([]);
        $this->driver->unbind('topic', ['*']);
    }

    /**
     *
     */
    public function test_close()
    {
        $this->connection->expects($this->once())->method('close');
        $this->channel->expects($this->once())->method('close');

        $this->driver->close();
        // close once
        $this->driver->close();
    }
}
