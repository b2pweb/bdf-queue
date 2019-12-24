<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * TimeLimiterMiddlewareReceiver
 */
class TimeLimiterReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * Time limit in second
     *
     * @var int
     */
    private $limit;

    /**
     * The end time
     *
     * @var int
     */
    private $endTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * TimeLimiterMiddlewareReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param int $limit  Time limit in second
     * @param LoggerInterface|null $logger
     */
    public function __construct(ReceiverInterface $delegate, int $limit, LoggerInterface $logger = null)
    {
        $this->delegate = $delegate;
        $this->logger = $logger;
        $this->limit = $limit;
        $this->endTime = $limit + microtime(true);
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->delegate->receive($message, $consumer);

        $this->checkRunningTime($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->delegate->receiveTimeout($consumer);

        $this->checkRunningTime($consumer);
    }

    /**
     * Check the memory usage and stop receiver if memory is reached
     *
     * @param ConsumerInterface $receiver
     */
    public function checkRunningTime(ConsumerInterface $receiver): void
    {
        if ($this->endTime >= microtime(true)) {
            return;
        }

        $receiver->stop();

        if (null !== $this->logger) {
            $this->logger->info('Receiver stopped due to time limit of {timeLimit}s reached', ['timeLimit' => $this->limit]);
        }
    }
}
