<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
class LimitTimeWhenEmptyReceiver implements ReceiverInterface
{
    use DelegateHelper;

    private int $limit;
    private ?int $endTime;
    private ?LoggerInterface $logger;

    /**
     * TimeLimiterMiddlewareReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param int $limit  Time limit in second
     * @param LoggerInterface|null $logger
     */
    public function __construct(/*int $limit, LoggerInterface $logger = null*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->endTime = null;
        $this->limit = $args[$index++];
        $this->logger = $args[$index] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->endTime = null;

        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);

        if (null === $this->endTime) {
            $this->endTime = $this->limit + time();
        }

        if ($this->endTime >= time()) {
            return;
        }

        $consumer->stop();

        if (null !== $this->logger) {
            $this->logger->info('Receiver stopped due to empty time limit of {timeLimit}s reached', ['timeLimit' => $this->limit]);
        }
    }
}
