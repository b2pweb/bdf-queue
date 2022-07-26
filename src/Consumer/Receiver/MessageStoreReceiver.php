<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobRepositoryAdapter;
use Bdf\Queue\Failer\FailedJobRepositoryInterface;
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
     * @var FailedJobRepositoryInterface
     */
    private $repository;

    /**
     * StopWhenEmptyReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param FailedJobStorageInterface $storage
     * @param LoggerInterface $logger
     */
    public function __construct(/*FailedJobStorageInterface $storage, LoggerInterface $logger*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->repository = FailedJobRepositoryAdapter::adapt($args[$index++]);
        $this->logger = $args[$index];
    }

    /**
     * {@inheritdoc}
     *
     * @param EnvelopeInterface $message
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;

        try {
            $next->receive($message, $consumer);
        } catch (\Throwable $exception) {
            if ($message->message()->noStore() !== true) {
                $this->logger->info('Storing the job "'.$message->message()->name().'".');

                $this->repository->store(FailedJob::create($message->message(), $exception));
            }

            throw $exception;
        }
    }
}
