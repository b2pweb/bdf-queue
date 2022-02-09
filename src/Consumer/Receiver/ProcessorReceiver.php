<?php

namespace Bdf\Queue\Consumer\Receiver;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Processor\ProcessorResolverInterface;

/**
 * The outlet of the receiver stack: find and execute a processor
 */
class ProcessorReceiver implements ReceiverInterface
{
    /**
     * The target resolver.
     *
     * @var ProcessorResolverInterface
     */
    private $resolver;

    /**
     * Create a new queue worker.
     *
     * @param ProcessorResolverInterface $resolver
     */
    public function __construct(ProcessorResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     *
     * @param EnvelopeInterface $message
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        // Don't handle job deleted by extension
        if ($message->isDeleted()) {
            return;
        }

        try {
            $processor = $this->resolver->resolve($message);
            $processor->process($message);

            $message->acknowledge();
        } catch (\Throwable $exception) {
            $message->reject();

            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void
    {

    }
}
