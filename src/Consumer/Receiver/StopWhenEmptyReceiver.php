<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Psr\Log\LoggerInterface;

/**
 * Stop receiver when no more job is found.
 */
class StopWhenEmptyReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * StopWhenEmptyReceiver constructor.
     *
     * @param ReceiverInterface $delegate Previous receiver
     * @param null|LoggerInterface $logger Logger. Null value can be set instead of NullLogger
     */
    public function __construct(ReceiverInterface $delegate, LoggerInterface $logger = null)
    {
        $this->delegate = $delegate;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->delegate->receiveTimeout($consumer);

        $consumer->stop();

        if ($this->logger !== null) {
            $this->logger->info('The worker will stop for no consuming job');
        }
    }
}
