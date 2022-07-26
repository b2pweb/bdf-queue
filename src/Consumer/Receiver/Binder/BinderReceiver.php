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
    public function __construct(/*array $binders*/)
    {
        $args = func_get_args();
        $index = 0;

        if ($args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->binders = $args[$index];
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

        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);
    }

    /**
     * Register new binders
     *
     * @param BinderInterface[] $binders New binders to add
     * @internal
     */
    public function add(array $binders): void
    {
        $this->binders = array_merge($this->binders, $binders);
    }
}
