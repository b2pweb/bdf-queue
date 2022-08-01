<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\InteractEnvelopeInterface;
use Bdf\Queue\Message\QueuedMessage;
use Psr\Log\LoggerInterface;

/**
 * Retry a job by repushing in queue when failed.
 *
 * If the message reach the maximum retry count, the exception will be rethrown.
 *
 * @author seb
 *
 * @see MessageStoreReceiver For store failing jobs which reach the maximum retry count
 */
class RetryMessageReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * The max attempts for a job
     *
     * @var int
     */
    private $maxTries;

    /**
     * The waiting delay for retry the job
     *
     * @var int
     */
    private $delay;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * JobLoggerListener constructor.
     *
     * @param ReceiverInterface $delegate Previous receiver
     * @param LoggerInterface $logger Logger
     * @param int $maxTries Maximum number of retries, if not set on message
     * @param int $delay The retry delay in seconds. If the job fail, it will be retried after this delay.
     */
    public function __construct(/*LoggerInterface $logger, int $maxTries = 3, int $delay = 10*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->logger = $args[$index++];
        $this->maxTries = $args[$index++] ?? 3;
        $this->delay = $args[$index] ?? 10;
    }

    /**
     * {@inheritdoc}
     *
     * @param InteractEnvelopeInterface $message
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;

        try {
            $next->receive($message, $consumer);
        } catch (\Throwable $exception) {
            // Too many attemps: we dont retry the job and let the other middleware take care about it.
            if ($this->maxTriesReached($message->message())) {
                throw $exception;
            }

            $this->logger->notice('Sending the job "'.$message->message()->name().'" back to queue');

            $message->retry($this->delay);
        }
    }

    /**
     * Checks whether the attempt reached the max try number
     *
     * @param QueuedMessage $message
     *
     * @return bool
     */
    private function maxTriesReached($message)
    {
        $max = $message->maxTries() ?? $this->maxTries;

        return $max < $message->attempts();
    }
}
