<?php

namespace Bdf\Queue\Failer;

/**
 * In-memory repository for failed jobs
 */
class MemoryFailedJobRepository implements FailedJobRepositoryInterface
{
    /**
     * The prime connection
     *
     * @var FailedJob[]
     */
    private $storage = [];

    /**
     * The prime connection
     *
     * @var FailedJob[]
     */
    private $index = 1;

    /**
     * {@inheritdoc}
     */
    public function store(FailedJob $job): void
    {
        $toStore = clone $job;
        $toStore->id = $this->index++;

        $this->storage[$toStore->id] = $toStore;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): iterable
    {
        return array_values($this->storage);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        return $this->findById($id);
    }

    /**
     * {@inheritdoc}
     */
    public function forget($id)
    {
        $exists = isset($this->storage[$id]);
        unset($this->storage[$id]);

        return $exists;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->storage = [];
    }

    /**
     * {@inheritdoc}
     */
    public function findById($id): ?FailedJob
    {
        return $this->storage[$id] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function search(FailedJobCriteria $criteria): iterable
    {
        return array_filter($this->storage, [$criteria, 'match']);
    }

    /**
     * {@inheritdoc}
     */
    public function purge(FailedJobCriteria $criteria): int
    {
        $removed = 0;

        foreach ($this->storage as $key => $job) {
            if ($criteria->match($job)) {
                unset($this->storage[$key]);
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(FailedJob $job): bool
    {
        return $this->forget($job->id);
    }
}
