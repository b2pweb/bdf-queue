<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Store failing job (i.e. throwing an exception), into a failed job storage.
 * The exception will be rethrown.
 *
 * If the message is marked as "no store", the message will not be stored
 *
 * @see \Bdf\Queue\Message\Message::noStore() For check if the message should be stored when failed
 */
class MessageStoreReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FailedJobStorageInterface
     */
    private $storage;

    /**
     * StopWhenEmptyReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param FailedJobStorageInterface $storage
     * @param LoggerInterface $logger
     */
    public function __construct(ReceiverInterface $delegate, FailedJobStorageInterface $storage, LoggerInterface $logger)
    {
        $this->delegate = $delegate;
        $this->storage = $storage;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @param EnvelopeInterface $envelope
     */
    public function receive($envelope, ConsumerInterface $consumer): void
    {
        try {
            $this->delegate->receive($envelope, $consumer);
        } catch (\Throwable $exception) {
            if ($envelope->message()->noStore() !== true) {
                $this->logger->info('Storing the job "'.$envelope->message()->name().'".');

                $this->storage->store(FailedJob::create($envelope->message(), $exception));
            }

            throw $exception;
        }
    }
}
