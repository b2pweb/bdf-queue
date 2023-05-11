<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Serializer\Serializer;
use Enqueue\ConnectionFactoryFactoryInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Context;
use PHPUnit\Framework\TestCase;

/**
 * Class EnqueueConnectionTest
 */
class EnqueueConnectionTest extends TestCase
{
    /**
     * @var EnqueueConnection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = new EnqueueConnection('name', new Serializer());
    }

    /**
     *
     */
    public function test_close()
    {
        $context = $this->createMock(Context::class);
        $contextFactory = $this->createMock(ConnectionFactory::class);
        $contextFactory->expects($this->any())->method('createContext')->willReturn($context);

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->expects($this->exactly(2))->method('create')->willReturn($contextFactory);

        $this->connection = new EnqueueConnection('name', new Serializer(), $factory);
        $this->connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->connection->queue()->pop('my-queue', -1); // Connect to the driver
        $this->connection->close();
        $this->connection->queue()->pop('my-queue', -1); // Connect to the driver
    }

    /**
     *
     */
    public function test_declareQueue_error()
    {
        $this->expectException(ConnectionException::class);

        $context = $this->createMock(AmqpContext::class);
        $contextFactory = $this->createMock(ConnectionFactory::class);
        $contextFactory->expects($this->any())->method('createContext')->willReturn($context);

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->willReturn($contextFactory);

        $context->expects($this->once())->method('createQueue')->willThrowException(new \Exception('test'));

        $this->connection = new EnqueueConnection('name', new Serializer(), $factory);
        $this->connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->connection->declareQueue('test');
    }

    /**
     *
     */
    public function test_deleteQueue_error()
    {
        $this->expectException(ConnectionException::class);

        $context = $this->createMock(AmqpContext::class);
        $contextFactory = $this->createMock(ConnectionFactory::class);
        $contextFactory->expects($this->any())->method('createContext')->willReturn($context);

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->willReturn($contextFactory);

        $context->expects($this->once())->method('createQueue')->willReturn($this->createMock(AmqpQueue::class));
        $context->expects($this->once())->method('deleteQueue')->willThrowException(new \Exception('test'));

        $this->connection = new EnqueueConnection('name', new Serializer(), $factory);
        $this->connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->connection->deleteQueue('test');
    }

    /**
     *
     */
    public function test_declareTopic_error()
    {
        $this->expectException(ConnectionException::class);

        $context = $this->createMock(AmqpContext::class);
        $contextFactory = $this->createMock(ConnectionFactory::class);
        $contextFactory->expects($this->any())->method('createContext')->willReturn($context);

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->willReturn($contextFactory);

        $context->expects($this->once())->method('createTopic')->willThrowException(new \Exception('test'));

        $this->connection = new EnqueueConnection('name', new Serializer(), $factory);
        $this->connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->connection->declareTopic('test');
    }

    /**
     *
     */
    public function test_deleteTopic_error()
    {
        $this->expectException(ConnectionException::class);

        $context = $this->createMock(AmqpContext::class);
        $contextFactory = $this->createMock(ConnectionFactory::class);
        $contextFactory->expects($this->any())->method('createContext')->willReturn($context);

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->willReturn($contextFactory);

        $context->expects($this->once())->method('createTopic')->willReturn($this->createMock(AmqpTopic::class));
        $context->expects($this->once())->method('deleteTopic')->willThrowException(new \Exception('test'));

        $this->connection = new EnqueueConnection('name', new Serializer(), $factory);
        $this->connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->connection->deleteTopic('test');
    }

    /**
     * @dataProvider provideConfigDsn
     */
    public function test_configToDsn($config, $dsn)
    {
        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);

        $connection = new EnqueueConnection('name', new Serializer(), $factory);

        $factory->expects($this->once())->method('create')->with($dsn);
        $connection->setConfig($config);
        $connection->queue()->pop('', -1);
    }

    /**
     * @return array
     */
    public function provideConfigDsn()
    {
        return [
            [['vendor' => 'redis'], 'redis:?auto_declare=0'],
            [['vendor' => 'redis', 'host' => '127.0.0.1'], 'redis://127.0.0.1?auto_declare=0'],
            [['vendor' => 'redis', 'host' => '127.0.0.1', 'user' => 'my-user'], 'redis://my-user@127.0.0.1?auto_declare=0'],
            [['vendor' => 'redis', 'host' => '127.0.0.1', 'user' => 'my-user', 'password' => 'pass'], 'redis://my-user:pass@127.0.0.1?auto_declare=0'],
            [['vendor' => 'redis', 'foo' => 'bar'], 'redis:?foo=bar&auto_declare=0'],
        ];
    }
}
