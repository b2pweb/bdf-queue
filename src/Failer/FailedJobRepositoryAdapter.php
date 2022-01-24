<?php

namespace Bdf\Queue\Failer;

/**
 * Adapt legacy FailedJobStorageInterface to new FailedJobRepositoryInterface
 * Filters will be applied "in memory", so it will be less effective than native handling
 */
final class FailedJobRepositoryAdapter implements FailedJobRepositoryInterface
{
    /**
     * @var FailedJobStorageInterface
     */
    private $storage;

    /**
     * @param FailedJobStorageInterface $storage
     */
    private function __construct(FailedJobStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function store(FailedJob $job): void
    {
        $this->storage->store($job);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): iterable
    {
        return $this->storage->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findById($id): ?FailedJob
    {
        return $this->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function search(FailedJobCriteria $criteria): iterable
    {
        if ($criteria->toArray() === []) {
            yield from $this->all();

            return;
        }

        foreach ($this->all() as $job) {
            if ($criteria->match($job)) {
                yield $job;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function purge(FailedJobCriteria $criteria): int
    {
        if ($criteria->toArray() === []) {
            $this->flush();
            return -1;
        }

        $count = 0;

        foreach ($this->search($criteria) as $job) {
            $this->delete($job);
            ++$count;
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(FailedJob $job): bool
    {
        return $this->forget($job->id);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        return $this->storage->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function forget($id)
    {
        return $this->storage->forget($id);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->storage->flush();
    }

    /**
     * Adapt a legacy storage to repository interface
     *
     * @param FailedJobStorageInterface $storage
     *
     * @return FailedJobRepositoryInterface
     */
    public static function adapt(FailedJobStorageInterface $storage): FailedJobRepositoryInterface
    {
        if ($storage instanceof FailedJobRepositoryInterface) {
            return $storage;
        }

        return new self($storage);
    }
}
