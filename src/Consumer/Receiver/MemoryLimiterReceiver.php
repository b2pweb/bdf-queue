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
    public function __construct(/*int $limit, LoggerInterface $logger = null, callable $memoryResolver = null*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->limit = $args[$index++];
        $this->logger = $args[$index++] ?? null;
        $this->memoryResolver = $args[$index] ?? function () {
            return \memory_get_usage();
        };
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);

        $this->checkMemoryUsage($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);

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
