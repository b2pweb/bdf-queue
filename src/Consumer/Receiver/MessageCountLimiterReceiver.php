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
    public function __construct(ReceiverInterface $delegate, int $limit, LoggerInterface $logger = null)
    {
        $this->delegate = $delegate;
        $this->limit = $limit;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->delegate->receive($message, $consumer);

        if (++$this->receivedMessages >= $this->limit) {
            $consumer->stop();

            if (null !== $this->logger) {
                $this->logger->info('Receiver stopped due to maximum count of {count} exceeded', ['count' => $this->limit]);
            }
        }
    }
}
