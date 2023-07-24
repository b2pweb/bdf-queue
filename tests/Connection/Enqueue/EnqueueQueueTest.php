<?php

namespace Bdf\Queue\Connection\Enqueue;

use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Serializer\Serializer;
use PHPUnit\Framework\TestCase;

/**
 * Class EnqueueQueueTest
 */
class EnqueueQueueTest extends TestCase
{
    /**
     * @var EnqueueQueue
     */
    private $queue;

    protected function setUp(): void
    {
        $connection = new EnqueueConnection('name', new Serializer());
        $connection->setConfig([
            'vendor' => 'file',
            'queue' => 'tmp/test-enqueue-driver'
        ]);

        $this->queue = $connection->queue();
    }

    /**
     *
     */
    public function test_getters()
    {
        $this->assertNull($this->queue->count(''));
        $this->assertSame([], $this->queue->stats());
    }

    /**
     *
     */
    public function test_functional_push_pop()
    {
        $message = new Message(['foo' => 'bar']);
        $message->setQueue('my-queue');

        $this->queue->push($message);

        $queuedMessage = $this->queue->pop('my-queue', -1);

        $this->assertInstanceOf(QueueEnvelope::class, $queuedMessage);
        $this->assertSame(['foo' => 'bar'], $queuedMessage->message()->data());
        $this->assertEquals('my-queue', $queuedMessage->message()->queue());

        $this->assertNull($this->queue->pop('my-queue', -1));
    }

    /**
     *
     */
    public function test_functional_release()
    {
        $message = new Message(['foo' => 'bar']);
        $message->setQueue('my-queue');

        $this->queue->push($message);

        $queuedMessage = $this->queue->pop('my-queue', -1);
        $this->queue->release($queuedMessage->message());

        $this->assertEquals($queuedMessage, $this->queue->pop('my-queue', -1));
        $this->assertNull($this->queue->pop('my-queue', -1));
    }

    /**
     *
     */
    public function test_functional_acknowledge()
    {
        $message = new Message(['foo' => 'bar']);
        $message->setQueue('my-queue');

        $this->queue->push($message);

        $queuedMessage = $this->queue->pop('my-queue', -1);
        $this->queue->acknowledge($queuedMessage->message());

        $this->assertNull($this->queue->pop('my-queue', -1));
    }
//
//    /**
//     *
//     */
//    public function test_close()
//    {
//        $this->queue->setConfig([
//            'vendor' => 'file',
//            'queue' => 'tmp/test-enqueue-driver'
//        ]);
//
//        $this->queue->pop('my-queue', -1); // Connect to the driver
//
//        $this->assertAttributeInstanceOf(FsContext::class, 'context', $this->queue);
//        $this->queue->close();
//        $this->assertAttributeEmpty('context', $this->queue);
//    }
//
//    /**
//     * @dataProvider provideConfigDsn
//     */
//    public function test_configToDsn($config, $dsn)
//    {
//        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
//
//        $driver = new EnqueueDriver(new Serializer(), $factory);
//
//        $factory->expects($this->once())->method('create')->with($dsn);
//        $driver->setConfig($config);
//        $driver->pop('', -1);
//    }
//
//    /**
//     * @return array
//     */
//    public function provideConfigDsn()
//    {
//        return [
//            [['vendor' => 'redis'], 'redis:?auto_declare=0'],
//            [['vendor' => 'redis', 'host' => '127.0.0.1'], 'redis://127.0.0.1?auto_declare=0'],
//            [['vendor' => 'redis', 'host' => '127.0.0.1', 'user' => 'my-user'], 'redis://my-user@127.0.0.1?auto_declare=0'],
//            [['vendor' => 'redis', 'host' => '127.0.0.1', 'user' => 'my-user', 'password' => 'pass'], 'redis://my-user:pass@127.0.0.1?auto_declare=0'],
//            [['vendor' => 'redis', 'foo' => 'bar'], 'redis:?foo=bar&auto_declare=0'],
//        ];
//    }
}
