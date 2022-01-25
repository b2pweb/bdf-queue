<?php

namespace Bdf\Queue\Failer;

/**
 * Store and requests for FailedJob
 */
interface FailedJobRepositoryInterface extends FailedJobStorageInterface
{
    /**
     * Store a failed job.
     *
     * @param FailedJob
     *
     * @return void
     */
    public function store(FailedJob $job): void;

    /**
     * List all failed jobs.
     *
     * @return iterable<FailedJob>
     */
    public function all(): iterable;

    /**
     * Get a single failed job by its id
     *
     * @param mixed $id The job id
     *
     * @return FailedJob|null The job instance, or null if not found
     */
    public function findById($id): ?FailedJob;

    /**
     * Search for failed jobs using a criteria
     * For dump all jobs, use `all()` instead
     *
     * <code>
     * $jobs = $storage->search((new FailedJobCriteria)
     *     ->name('Foo*')
     *     ->failedAt(new DateTime('2020-10-15'), '<=')
     * );
     *
     * foreach ($jobs as $job) {
     *     // ...
     * }
     * </code>
     *
     * @param FailedJobCriteria $criteria The search criteria
     *
     * @return iterable<FailedJob>
     */
    public function search(FailedJobCriteria $criteria): iterable;

    /**
     * Purge failed jobs using a criteria
     *
     * <code>
     * $count = $storage->purge((new FailedJobCriteria)
     *     ->name('Foo*')
     *     ->failedAt(new DateTime('2020-10-15'), '<=')
     * );
     *
     * if ($count > 0) {
     *     echo $count, ' jobs removed';
     * }
     * </code>
     *
     * @param FailedJobCriteria $criteria
     *
     * @return positive-int
     */
    public function purge(FailedJobCriteria $criteria): int;

    /**
     * Delete one failed job
     *
     * @param FailedJob $job Job to delete
     *
     * @return bool true on success, or false if the job is not found
     */
    public function delete(FailedJob $job): bool;
}
