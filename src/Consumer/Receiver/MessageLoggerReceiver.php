<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\QueuedMessage;
use Psr\Log\LoggerInterface;

/**
 * Logs execution of each jobs (starting and ending)
 *
 * @author seb
 */
class MessageLoggerReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * JobLoggerReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param LoggerInterface $logger
     */
    public function __construct(/*LoggerInterface $logger*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

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
            $this->logger->info($this->format($message->message(), ' starting'), [
                'queued at' => $message->message()->queuedAt()->format('Y/m/d H:i:s')
            ]);
            $this->logger->debug($message->message()->raw());

            $next->receive($message, $consumer);

            $this->logger->info($this->format($message->message(), ' succeed'));
        } catch (\Throwable $exception) {
            $this->logger->critical($this->format($message->message(), ' failed: '.$exception->getMessage()), ['exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
        $this->logger->info('stopping worker');

        $next = $this->delegate ?? $consumer;
        $next->receiveStop($consumer);
    }

    /**
     * Get string representation
     *
     * @param QueuedMessage $message
     * @param string $additionals
     *
     * @return string
     */
    private function format($message, $additionals = '')
    {
        return "[{$message->connection()}::{$message->source()}] \"{$message->name()}\"${additionals}";
    }
}
