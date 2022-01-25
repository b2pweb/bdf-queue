<?php

namespace Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobRepositoryAdapter;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Message\QueuedMessage;
use PHPUnit\Framework\TestCase;

class FailedJobRepositoryAdapterTest extends TestCase
{
    /**
     * @var FailedJobRepositoryAdapter
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = FailedJobRepositoryAdapter::adapt(new class implements FailedJobStorageInterface {
            public $storage = [];
            public $lastId = 0;

            public function store(FailedJob $job)
            {
                $job->id = ++$this->lastId;
                $this->storage[$this->lastId] = $job;
            }

            public function all()
            {
                return $this->storage;
            }

            public function find($id)
            {
                return $this->storage[$id] ?? null;
            }

            public function forget($id)
            {
                if (isset($this->storage[$id])) {
                    unset($this->storage[$id]);
                    return true;
                }

                return false;
            }

            public function flush()
            {
                $this->storage = [];
            }
        });
    }

    /**
     *
     */
    public function test_store_get()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->repository->store($created);

        $this->assertSame($this->repository->find(1), $this->repository->findById(1));
    }

    /**
     *
     */
    public function test_delete()
    {
        $this->repository->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->repository->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $job = $this->repository->findById(1);

        $result = $this->repository->delete($job);
        $jobs = $this->repository->all();

        $this->assertTrue($result);
        $this->assertSame(1, count($jobs));

        $this->assertFalse($this->repository->delete($job));
    }

    /**
     * @dataProvider provideSearchCriteria
     */
    public function test_search(FailedJobCriteria $criteria, array $expectedIds)
    {
        $this->repository->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue1',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
        ]));
        $this->repository->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue2',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
        ]));
        $this->repository->store(new FailedJob([
            'connection' => 'conn2',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
        ]));

        $this->assertEqualsCanonicalizing($expectedIds, array_map(function (FailedJob $job) {
            return $job->id;
        }, iterator_to_array($this->repository->search($criteria))));
    }

    /**
     * @dataProvider provideSearchCriteria
     */
    public function test_purge(FailedJobCriteria $criteria, array $expectedIds)
    {
        $this->repository->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue1',
            'name' => 'Foo',
            'failedAt' => new \DateTime('2022-01-15 15:02:30'),
            'error' => 'my error',
        ]));
        $this->repository->store(new FailedJob([
            'connection' => 'conn1',
            'queue' => 'queue2',
            'name' => 'Bar',
            'failedAt' => new \DateTime('2022-01-15 22:14:15'),
            'error' => 'my other error',
        ]));
        $this->repository->store(new FailedJob([
            'connection' => 'conn2',
            'queue' => 'queue2',
            'name' => 'Baz',
            'error' => 'hello world',
            'failedAt' => new \DateTime('2021-12-21 08:00:35'),
        ]));

        $ids = function () {
            return array_map(
                function (FailedJob $job) { return $job->id; },
                $this->repository->all()
            );
        };

        $remainingIds = array_diff($ids(), $expectedIds);

        $this->assertEquals($criteria->toArray() ? count($expectedIds) : -1, $this->repository->purge($criteria));
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
