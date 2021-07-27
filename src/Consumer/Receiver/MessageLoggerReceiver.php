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
     * @param EnvelopeInterface $envelope
     */
    public function receive($envelope, ConsumerInterface $consumer): void
    {
        try {
            $this->logger->info($this->format($envelope->message(), ' starting'), [
                'queued at' => $envelope->message()->queuedAt()->format('Y/m/d H:i:s')
            ]);
            $this->logger->debug($envelope->message()->raw());

            $this->delegate->receive($envelope, $consumer);

            $this->logger->info($this->format($envelope->message(), ' succeed'));
        } catch (\Throwable $exception) {
            $this->logger->critical($this->format($envelope->message(), ' failed: '.$exception->getMessage()), ['exception' => $exception]);

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