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
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * StopWhenEmptyReceiver constructor.
     *
     * @param ReceiverInterface|LoggerInterface|null $delegate Previous receiver
     * @param LoggerInterface|null $logger Logger. Null value can be set instead of NullLogger
     */
    public function __construct($delegate = null, LoggerInterface $logger = null)
    {
        if ($delegate instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $delegate;
            $this->logger = $logger;
        } else {
            $this->logger = $delegate;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);

        $consumer->stop();

        if ($this->logger !== null) {
            $this->logger->info('The worker will stop for no consuming job');
        }
    }
}
