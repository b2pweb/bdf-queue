<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * MessageCountLimiterMiddlewareReceiver
 */
class MessageCountLimiterReceiver implements ReceiverInterface
{
    use DelegateHelper;

    private $limit;
    private $logger;
    private $receivedMessages = 0;

    /**
     * MessageCountLimiterMiddlewareReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param int $limit
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

        $this->limit = $args[$index++];
        $this->logger = $args[$index] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);

        if (++$this->receivedMessages >= $this->limit) {
            $consumer->stop();

            if (null !== $this->logger) {
                $this->logger->info('Receiver stopped due to maximum count of {count} exceeded', ['count' => $this->limit]);
            }
        }
    }
}
