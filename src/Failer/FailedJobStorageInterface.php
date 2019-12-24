<?php

namespace Bdf\Queue\Failer;

/**
 * FailedJobStorageInterface
 */
interface FailedJobStorageInterface
{
    /**
     * Store a failed job.
     *
     * @param FailedJob
     */
    public function store(FailedJob $job);

    /**
     * Get a list of all of the failed jobs.
     *
     * @return FailedJob[]|iterable
     */
    public function all();

    /**
     * Get a single failed job.
     *
     * @param mixed  $id
     * 
     * @return FailedJob
     */
    public function find($id);

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed  $id
     * 
     * @return bool
     */
    public function forget($id);

    /**
     * Flush all of the failed jobs from storage.
     */
    public function flush();
}
