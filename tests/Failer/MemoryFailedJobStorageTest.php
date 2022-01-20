<?php

namespace Bdf\Queue\Failer;

use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Failed
 */
class MemoryFailedJobStorageTest extends TestCase
{
    /**
     * @var MemoryFailedJobStorage
     */
    private $provider;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->provider = new MemoryFailedJobStorage();
    }

    /**
     *
     */
    public function test_create_log()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->assertSame(null, $created->id);
        $this->assertSame($message->name(), $created->name);
        $this->assertSame($message->connection(), $created->connection);
        $this->assertSame($message->queue(), $created->queue);
        $this->assertSame($message->toQueue(), $created->messageContent);
        $this->assertSame(QueuedMessage::class, $created->messageClass);
        $this->assertSame('foo', $created->error);
        $this->assertSame(0, $created->attempts);
        $this->assertInstanceOf(\DateTime::class, $created->failedAt);
        $this->assertInstanceOf(\DateTime::class, $created->lastFailedAt);
    }

    /**
     *
     */
    public function test_to_message()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');
        $message->setHeaders([
            'failer-failed-at' => new \DateTime(),
        ]);

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->assertEquals($message, $created->toMessage());
    }

    /**
     *
     */
    public function test_to_message_with_retry()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');
        $message->setHeaders([
            'failer-failed-at' => new \DateTime(),
            'failer-attempts' => 1,
        ]);

        $created = FailedJob::create($message, new \Exception('foo'));
        $created->attempts++;
        $message->addHeader('failer-attempts', 2);

        $this->assertEquals($message, $created->toMessage());
    }

    /**
     *
     */
    public function test_store_log()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->provider->store($created);
        $job = $this->provider->find(1);

        $this->assertSame(1, $job->id);
        $this->assertSame($created->name, $job->name);
        $this->assertSame($created->connection, $job->connection);
        $this->assertSame($created->queue, $job->queue);
        $this->assertSame($message->toQueue(), $created->messageContent);
        $this->assertSame(QueuedMessage::class, $created->messageClass);
        $this->assertSame($created->error, $job->error);
        $this->assertInstanceOf(\DateTime::class, $job->failedAt);
    }

    /**
     *
     */
    public function test_all()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $jobs = $this->provider->all();

        $this->assertSame(2, count($jobs));
        $this->assertSame('queue1', $jobs[0]->queue);
        $this->assertSame('queue2', $jobs[1]->queue);
    }

    /**
     *
     */
    public function test_forget()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $result = $this->provider->forget(1);
        $jobs = $this->provider->all();

        $this->assertTrue($result);
        $this->assertSame(1, count($jobs));
    }

    /**
     *
     */
    public function test_flush()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $this->provider->flush();

        $this->assertSame(0, count($this->provider->all()));
    }
}