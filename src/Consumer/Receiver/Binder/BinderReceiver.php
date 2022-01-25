<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Receiver for apply binders to the message before calling the next receiver
 * Stops the binding when the first binder match
 *
 * @see BinderInterface
 */
final class BinderReceiver implements ReceiverInterface
{
    /**
     * @var ReceiverInterface
     */
    private $next;

    /**
     * @var BinderInterface[]
     */
    private $binders;


    /**
     * BinderReceiver constructor.
     *
     * @param ReceiverInterface $next The receiver to call after binding
     * @param BinderInterface[] $binders List of binders to apply to the message
     */
    public function __construct(ReceiverInterface $next, array $binders)
    {
        $this->next = $next;
        $this->binders = $binders;
    }

    /**
     * {@inheritdoc}
     *
     * @param EnvelopeInterface $message
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        foreach ($this->binders as $binder) {
            if ($binder->bind($message->message())) {
                break;
            }
        }

        $this->next->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->next->receiveTimeout($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(): void
    {
        $this->next->receiveStop();
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void
    {
        $this->next->terminate();
    }
}
