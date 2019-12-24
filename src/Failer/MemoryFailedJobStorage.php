<?php

namespace Bdf\Queue\Failer;

/**
 * MemoryFailedJobStorage
 */
class MemoryFailedJobStorage implements FailedJobStorageInterface
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
    public function store(FailedJob $job)
    {
        $toStore = clone $job;
        $toStore->id = $this->index++;

        $this->storage[$toStore->id] = $toStore;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return array_values($this->storage);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        return $this->storage[$id] ?? null;
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
}
