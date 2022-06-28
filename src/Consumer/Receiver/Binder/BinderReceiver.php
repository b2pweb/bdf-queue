<?php

namespace Bdf\Queue\Consumer\Receiver\Binder;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\DelegateHelper;
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
    use DelegateHelper;

    /**
     * @var BinderInterface[]
     */
    private $binders;


    /**
     * BinderReceiver constructor.
     *
     * @param ReceiverInterface $delegate The receiver to call after binding
     * @param BinderInterface[] $binders List of binders to apply to the message
     */
    public function __construct(ReceiverInterface $delegate, array $binders)
    {
        $this->delegate = $delegate;
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

        $this->delegate->receive($message, $consumer);
    }
}
