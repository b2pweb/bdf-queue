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
     * @var MemoryFailedJobRepository
     */
    private $provider;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->provider = new MemoryFailedJobRepository();
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
        $this->assertInstanceOf(\DateTime::class, $created->firstFailedAt);
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

        $this->assertSame($job, $this->provider->findById(1));

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
    public function test_delete()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $job = $this->provider->findById(1);

        $result = $this->provider->delete($job);
        $jobs = $this->provider->all();

        $this->assertTrue($result);
        $this->assertSame(1, count($jobs));

        $this->assertFalse($this->provider->delete($job));
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

    /**
     * @dataProvider provideSearchCriteria
     */
    public function test_search(FailedJobCriteria $criteria, array $expectedIds)
    {
        $this->provider->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue1',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue2',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'conn2',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
        ]));

        $this->assertEqualsCanonicalizing($expectedIds, array_map(function (FailedJob $job) {
            return $job->id;
        }, $this->provider->search($criteria)));
    }

    /**
     * @dataProvider provideSearchCriteria
     */
    public function test_purge(FailedJobCriteria $criteria, array $expectedIds)
    {
        $this->provider->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue1',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue2',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'conn2',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
        ]));

        $ids = function () {
            return array_map(
                function (FailedJob $job) { return $job->id; },
                $this->provider->all()
            );
        };

        $remainingIds = array_diff($ids(), $expectedIds);

        $this->assertEquals(count($expectedIds), $this->provider->purge($criteria));
        $this->assertEqualsCanonicalizing($remainingIds, $ids());
    }

    public function provideSearchCriteria()
    {
        return [
            'empty' => [new FailedJobCriteria(), [1, 2, 3]],
            'connection' => [(new FailedJobCriteria())->connection('conn1'), [1, 2]],
            'queue' => [(new FailedJobCriteria())->queue('queue2'), [2, 3]],
            'name' => [(new FailedJobCriteria())->name('Foo'), [1]],
            'name wildcard' => [(new FailedJobCriteria())->name('Ba*'), [2, 3]],
            'name case insensitive' => [(new FailedJobCriteria())->name('foo'), [1]],
            'error' => [(new FailedJobCriteria())->error('my error'), [1]],
            'error wildcard' => [(new FailedJobCriteria())->error('my*error'), [1, 2]],
            'error wildcard contains' => [(new FailedJobCriteria())->error('*or*'), [1, 2, 3]],
            'failedAt with date' => [(new FailedJobCriteria())->failedAt(new \DateTime('2022-01-15 22:14:15')), [2]],
            'failedAt with string' => [(new FailedJobCriteria())->failedAt('2022-01-15 22:14:15'), [2]],
            'failedAt with wildcard' => [(new FailedJobCriteria())->failedAt('2022-01-15*'), [1, 2]],
            'failedAt with operator' => [(new FailedJobCriteria())->failedAt('2022-01-01', '<'), [3]],
            'failedAt with operator on value' => [(new FailedJobCriteria())->failedAt('> 2022-01-01'), [1, 2]],
            'queue + connection' => [(new FailedJobCriteria())->connection('conn1')->queue('queue2'), [2]],
            'none match' => [(new FailedJobCriteria())->name('Foo')->error('*world*'), []],
        ];
    }
}
