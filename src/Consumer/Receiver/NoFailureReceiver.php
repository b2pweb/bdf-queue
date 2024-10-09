<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;

/**
 * Catch all exception raised by job handlers.
 *
 * Note: This receiver can make inoperative JobStoreReceiver and RetryJobReceiver
 *
 * @author seb
 */
class NoFailureReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * JobLoggerListener constructor.
     *
     * @param ReceiverInterface|null $delegate
     */
    public function __construct(?ReceiverInterface $delegate = null)
    {
        $this->delegate = $delegate;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;

        try {
            $next->receive($message, $consumer);
        } catch (\Exception $exception) {
        }
    }
}
