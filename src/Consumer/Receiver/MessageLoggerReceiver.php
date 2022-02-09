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
    public function __construct(ReceiverInterface $delegate, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->delegate = $delegate;
    }

    /**
     * {@inheritdoc}
     *
     * @param EnvelopeInterface $message
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        try {
            $this->logger->info($this->format($message->message(), ' starting'), [
                'queued at' => $message->message()->queuedAt()->format('Y/m/d H:i:s')
            ]);
            $this->logger->debug($message->message()->raw());

            $this->delegate->receive($message, $consumer);

            $this->logger->info($this->format($message->message(), ' succeed'));
        } catch (\Throwable $exception) {
            $this->logger->critical($this->format($message->message(), ' failed: '.$exception->getMessage()), ['exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(): void
    {
        $this->logger->info('stopping worker');

        $this->delegate->receiveStop();
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