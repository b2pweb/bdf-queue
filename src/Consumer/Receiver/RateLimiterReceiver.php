<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * Limit number of jobs which can be executed without break.
 *
 * If the limit is reach, the worker is force to sleep.
 * If there is no more job in queue (and the queue wait for new jobs), the job counter is reset.
 *
 * Note: a failed job will not sleep if limit is reached
 *
 * @author seb
 */
class RateLimiterReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The rate limit
     *
     * @var int
     */
    private $limit;

    /**
     * The sleep duration
     *
     * @var int
     */
    private $sleep;

    /**
     * The number of jobs done in one loop
     *
     * @var int
     */
    private $jobsDoneInLoop = 0;

    /**
     * JobLoggerListener constructor.
     *
     * @param ReceiverInterface $delegate
     * @param LoggerInterface $logger
     * @param int $limit
     * @param int $sleep
     */
    public function __construct(ReceiverInterface $delegate, LoggerInterface $logger, int $limit, int $sleep = 3)
    {
        $this->logger = $logger;
        $this->delegate = $delegate;
        $this->limit = $limit;
        $this->sleep = $sleep;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        try {
            $this->delegate->receive($message, $consumer);
        } finally {
            // Do not sleep when an exception is raised
            $this->jobsDoneInLoop++;
        }

        // If the number of jobs in one loop is over the limit,
        // the worker should sleep (returns false)
        if ($this->jobsDoneInLoop < $this->limit) {
            return;
        }

        $this->jobsDoneInLoop = 0;

        $this->logger->notice('The worker has reached its rate limit of '.$this->limit);

        $this->sleep();
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->jobsDoneInLoop = 0;

        $this->delegate->receiveTimeout($consumer);
    }

    /**
     * Force the receiver to sleep
     */
    public function sleep(): void
    {
        if ($this->sleep > 0) {
            sleep($this->sleep);
        }
    }
}
