<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * MemoryLimitMiddlewareReceiver
 */
class MemoryLimiterReceiver implements ReceiverInterface
{
    use DelegateHelper;

    private $limit;
    private $logger;
    private $memoryResolver;

    /**
     * MemoryLimitMiddlewareReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param int $limit
     * @param LoggerInterface|null $logger
     * @param callable|null $memoryResolver
     */
    public function __construct(ReceiverInterface $delegate, int $limit, LoggerInterface $logger = null, callable $memoryResolver = null)
    {
        $this->delegate = $delegate;
        $this->limit = $limit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?: function () {
            return \memory_get_usage();
        };
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->delegate->receive($message, $consumer);

        $this->checkMemoryUsage($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->delegate->receiveTimeout($consumer);

        $this->checkMemoryUsage($consumer);
    }

    /**
     * Check the memory usage and stop receiver if memory is reached
     *
     * @param ConsumerInterface $receiver
     */
    public function checkMemoryUsage(ConsumerInterface $receiver): void
    {
        if (($this->memoryResolver)() <= $this->limit) {
            return;
        }

        $receiver->stop();

        if (null !== $this->logger) {
            $this->logger->info('Receiver stopped due to memory limit of {limit} exceeded', ['limit' => $this->limit]);
        }
    }
}
