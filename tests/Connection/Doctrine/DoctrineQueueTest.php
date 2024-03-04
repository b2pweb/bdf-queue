<?php

namespace Bdf\Queue\Connection\Doctrine;

use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Message\QueueEnvelope;
use Bdf\Queue\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class DoctrineQueueTest extends TestCase
{
    /**
     * @var DoctrineConnection
     */
    private $connection;

    /**
     * @var DoctrineQueue
     */
    private $queue;
    
    /**
     * 
     */
    protected function setUp(): void
    {
        $this->connection = new DoctrineConnection('name', new JsonSerializer());
        $this->connection->setConfig(['table' => 'job', 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->schema();

        $this->queue = $this->connection->queue();
    }
    
    /**
     * 
     */
    protected function tearDown(): void
    {
        $this->connection->dropSchema();
    }

    /**
     *
     */
    public function test_connection()
    {
        $this->assertSame($this->connection, $this->queue->connection());
    }

    /**
     *
     */
    public function test_push_one_data()
    {
        $message = Message::createFromJob('test', 'foo', 'queue');
        $this->queue->push($message);

        $message = $this->queue->pop('queue', 0);

        $this->assertInstanceOf(QueueEnvelope::class, $message);
        $this->assertSame('foo', $message->message()->data());
        $this->assertStringContainsString('{"job":"test","data":"foo","queuedAt":{"date"', $message->message()->raw());
        $this->assertSame('queue', $message->message()->queue());
        $this->assertMatchesRegularExpression('/[\w\d\-]+/', $message->message()->internalJob()['id']);
    }

    /**
     *
     */
    public function test_push_error()
    {
        $this->expectException(ServerException::class);

        $message = Message::createFromJob('test', 'foo', 'queue');
        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->push($message);
    }

    /**
     *
     */
    public function test_pop_error()
    {
        $this->expectException(ServerException::class);

        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->pop('queue', 0);
    }

    /**
     *
     */
    public function test_stats()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->queue->pushRaw('{"data":"foo"}', 'queue', 10);
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        // reserved one
        $this->queue->pop('queue', 0);

        $stats = $this->queue->stats()['queues'][0];

        $this->assertSame('queue', $stats['queue']);
        $this->assertSame(3, $stats['jobs in queue']);
        $this->assertSame(1, $stats['jobs awaiting']);
        $this->assertSame(1, $stats['jobs running']);
        $this->assertSame(1, $stats['jobs delayed']);
    }

    /**
     *
     */
    public function test_pop_reserve_job()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        $this->queue->pop('queue', 0);

        $stats = $this->queue->stats()['queues'][0];

        $this->assertEquals(2, $stats['jobs in queue']);
        $this->assertEquals(1, $stats['jobs running']);
    }

    /**
     *
     */
    public function test_reserve()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        $this->queue->reserve(2, 'queue', 0);

        $stats = $this->queue->stats()['queues'][0];

        $this->assertEquals(3, $stats['jobs in queue']);
        $this->assertEquals(1, $stats['jobs awaiting']);
        $this->assertEquals(2, $stats['jobs running']);
    }

    /**
     *
     */
    public function test_pop_none()
    {
        $this->assertNull($this->queue->pop('queue', 0));
    }

    /**
     *
     */
    public function test_push_several_data()
    {
        $this->queue->pushRaw('{"data":"foo1"}', 'queue');
        $this->queue->pushRaw('{"data":"foo2"}', 'queue');

        $message = $this->queue->pop('queue', 0);
        $this->assertEquals('foo1', $message->message()->data());

        $message = $this->queue->pop('queue', 0);
        $this->assertEquals('foo2', $message->message()->data());
    }

    /**
     *
     */
    public function test_acknowledge_job()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        $message = $this->queue->pop('queue', 0);

        $this->queue->acknowledge($message->message());

        $stats = $this->queue->stats()['queues'][0];

        $this->assertEquals(1, $stats['jobs in queue']);
        $this->assertEquals(0, $stats['jobs running']);
    }

    /**
     *
     */
    public function test_acknowledge_error()
    {
        $this->expectException(ServerException::class);
        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->acknowledge((new QueuedMessage())->setInternalJob(['id' => 'foo']));
    }

    /**
     *
     */
    public function test_release_message()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        $message = $this->queue->pop('queue', 0);

        $this->queue->release($message->message());

        $stats = $this->queue->stats()['queues'][0];

        $this->assertEquals(1, $stats['jobs awaiting']);
        $this->assertEquals(0, $stats['jobs delayed']);
    }

    /**
     *
     */
    public function test_release_error()
    {
        $this->expectException(ServerException::class);
        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->release((new QueuedMessage())->setInternalJob(['id' => 'foo']));
    }

    /**
     *
     */
    public function test_release_message_with_delay()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue');

        $message = $this->queue->pop('queue', 0);
        $message->message()->setDelay(20);

        $this->queue->release($message->message());
        $available = new \DateTime();
        $available->modify('20 second');

        $stats = $this->queue->stats()['queues'][0];
        $this->assertEquals(0, $stats['jobs awaiting']);
        $this->assertEquals(1, $stats['jobs delayed']);

        /** @var array $row */
        $row = $this->connection->queue()->peek('queue')[0]->internalJob();

        $this->assertEquals(0, $row['reserved']);
        $this->assertEquals($available->format('Y-m-d H:i:s'), $row['available_at']->format('Y-m-d H:i:s'));
    }

    /**
     *
     */
    public function test_delayed_job()
    {
        $this->queue->pushRaw('{"data":"foo"}', 'queue', 1);

        $message = $this->queue->pop('queue', 0);
        $this->assertNull($message);

        sleep(1);

        $message = $this->queue->pop('queue', 0);
        $this->assertEquals('foo', $message->message()->data());
    }

    /**
     *
     */
    public function test_count()
    {
        $this->assertEquals(0, $this->queue->count('queue'));

        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->assertEquals(1, $this->queue->count('queue'));

        $this->queue->pushRaw('{"data":"foo"}', 'queue');
        $this->assertEquals(2, $this->queue->count('queue'));
    }

    /**
     *
     */
    public function test_count_error()
    {
        $this->expectException(ServerException::class);
        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->count('test');
    }

    /**
     *
     */
    public function test_peek_error()
    {
        $this->expectException(ServerException::class);
        $this->connection->setConfig(['table' => "\0", 'vendor' => 'pdo_sqlite', 'memory' => true]);
        $this->queue->peek('test');
    }

    /**
     *
     */
    public function test_peek()
    {
        $this->queue->pushRaw('{"data":"foo1"}', 'queue');
        sleep(1);
        $this->queue->pushRaw('{"data":"foo2"}', 'queue');

        $messages = $this->queue->peek('queue', 1);

        $this->assertCount(1, $messages);
        $this->assertSame('foo1', $messages[0]->data());

        $message = $this->queue->pop('queue', 0)->message();
        $this->assertSame('foo1', $message->data());


        $messages = $this->queue->peek('queue', 1, 2);

        $this->assertCount(1, $messages);
        $this->assertSame('foo2', $messages[0]->data());
    }
}
