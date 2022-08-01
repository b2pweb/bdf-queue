<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * Receiver for measure execution time of jobs
 * This middle should be the last registered receiver to ensure that all the queue is benched
 *
 * This receiver will measure stats on a fixed number of jobs (by default 100).
 * Measurements are :
 * - Memory usage for execute all jobs
 * - Min, max and average execution time of each jobs (excluding the "queue overhead")
 * - Total execution time of all jobs (including "queue overhead")
 */
class BenchReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * The logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The bench stats
     *
     * @var array
     */
    private $stats = [];

    /**
     * @var array
     */
    private $current;

    /**
     * @var int
     */
    private $maxJobs;

    /**
     * @var int
     */
    private $maxHistory;


    /**
     * BenchJobs constructor.
     *
     * @param ReceiverInterface $delegate
     * @param LoggerInterface $logger
     * @param int $maxJobs The max number of jobs for one report
     * @param int $maxHistory Max number of stats to keep
     */
    public function __construct(/*LoggerInterface $logger, int $maxJobs = 100, int $maxHistory = 10*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->logger = $args[$index++];
        $this->maxJobs = $args[$index++] ?? 100;
        $this->maxHistory = $args[$index] ?? 10;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $startTime = microtime(true);

        if (!isset($this->current['start'])) {
            $this->current['start'] = $startTime;
            $this->current['memory-start'] = memory_get_peak_usage(false);
        }

        $next = $this->delegate ?? $consumer;

        try {
            $next->receive($message, $consumer);
        } finally {
            $this->current['end'] = $endTime = microtime(true);
            $this->current['times'][] = $endTime - $startTime;
            $this->current['memory-end'] = memory_get_peak_usage(false);

            $this->reset(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->reset(false);
        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->terminate($consumer);

        $this->report();
    }

    /**
     * Clear info collected
     *
     * @param bool $jobHandled
     */
    public function reset(bool $jobHandled)
    {
        // Create the report only if a break occurs or if the job limit is reached
        if (empty($this->current['times']) || ($jobHandled && count($this->current['times']) < $this->maxJobs)) {
            return;
        }

        $stats = $this->computeStats();
        $this->current = null;

        $this->push($stats);
        $this->display($stats);
    }

    /**
     * Report all collected benchmarks
     */
    public function report()
    {
        if (!empty($this->current['times'])) {
            $this->push($this->computeStats());
        }

        foreach ($this->stats as $stat) {
            $this->display($stat);
        }
    }

    /**
     * Display stats
     *
     * @param array $stats
     */
    private function display(array $stats)
    {
        $this->logger->info('Benchmark results', $stats);
    }

    /**
     * Push stats to history
     *
     * @param array $stats
     */
    private function push(array $stats)
    {
        $this->stats[] = $stats;

        if (count($this->stats) > $this->maxHistory) {
            array_shift($this->stats);
        }
    }

    /**
     * Compute the bench
     *
     * @return array
     */
    private function computeStats()
    {
        // total can be zero if all jobs are processed too quickly
        $total = array_sum($this->current['times']);
        $count = count($this->current['times']);

        return [
            [
                'Count' => $count,
                'Rate'  => $this->formatRate($count, $total),
                'Total rate' => $this->formatRate(count($this->current['times']), $this->current['end'] - $this->current['start']),
            ],
            'Time' => [
                'Start' => (new \DateTime())->setTimestamp((int) $this->current['start'])->format('H:i:s'),
                'End'   => (new \DateTime())->setTimestamp((int) $this->current['end'])->format('H:i:s'),
                'Min'   => $this->formatMilliseconds(min($this->current['times'])),
                'Max'   => $this->formatMilliseconds(max($this->current['times'])),
                'Avg'   => $this->formatMilliseconds($total / $count),
                'Total' => $this->formatMilliseconds($this->current['end'] - $this->current['start']),
            ],
            'Memory' => [
                'Start' => $this->formatMemory($this->current['memory-start']),
                'End' => $this->formatMemory($this->current['memory-end']),
            ]
        ];
    }

    /**
     * Format seconds to milliseconds
     *
     * @param float $seconds
     *
     * @return string
     */
    private function formatMilliseconds($seconds)
    {
        return round($seconds * 1000, 2).'ms';
    }

    /**
     * Format to mega bytes
     *
     * @param float $memory
     *
     * @return string
     */
    private function formatMemory($memory)
    {
        return round($memory / 1024 / 1024, 2).'MiB';
    }

    /**
     * Format the jobs rate
     *
     * @param integer $count Number of jobs
     * @param float $time The time to executes those jobs
     *
     * @return string
     */
    private function formatRate($count, $time)
    {
        return $time == 0 ? '-' : round($count / $time, 2).'j/s';
    }
}
