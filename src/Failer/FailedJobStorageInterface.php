<?php

namespace Bdf\Queue\Failer;

/**
 * FailedJobStorageInterface
 *
 * @deprecated Use FailedJobRepositoryInterface instead
 */
interface FailedJobStorageInterface
{
    /**
     * Store a failed job.
     *
     * @param FailedJob
     *
     * @return void
     */
    public function store(FailedJob $job);

    /**
     * List all failed jobs.
     *
     * @return iterable<FailedJob>
     */
    public function all();

    /**
     * Get a single failed job.
     *
     * @param mixed  $id
     * 
     * @return FailedJob
     * @deprecated Use findById() instead
     */
    public function find($id);

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed  $id
     * 
     * @return bool
     * @deprecated Use delete() instead
     */
    public function forget($id);

    /**
     * Flush all failed jobs from storage.
     *
     * @return void
     * @deprecated Use `purge()` instead
     */
    public function flush();
}
