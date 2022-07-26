<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Consumer\DelegateHelper;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Consumer\ConsumerInterface;

/**
 * MessageWatcherReceiver
 */
class MessageWatcherReceiver implements ReceiverInterface
{
    use DelegateHelper;

    /**
     * @var callable
     */
    private $callable;

    /**
     * The last received message
     *
     * @var object
     */
    private $message;

    /**
     * MessageWatcherReceiver constructor.
     *
     * @param ReceiverInterface $delegate
     * @param callable $callable
     */
    public function __construct(/*callable $callable = null*/)
    {
        $args = func_get_args();
        $index = 0;

        if (isset($args[0]) && $args[0] instanceof ReceiverInterface) {
            @trigger_error('Passing delegate in constructor of receiver is deprecated since 1.4', E_USER_DEPRECATED);
            $this->delegate = $args[0];
            ++$index;
        }

        $this->callable = $args[$index] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->message = $message;

        if ($this->callable !== null) {
            ($this->callable)($message, $consumer);
        }

        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        if ($this->callable !== null) {
            ($this->callable)(null, $consumer);
        }

        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);
    }

    /**
     * Gets the last received message
     *
     * @return object|null
     *
     * @deprecated Since 1.2: use MessageStacker as callable to get the last message. Will be removed in 2.0
     */
    public function getLastMessage()
    {
        @trigger_error("Since 1.2: use MessageStacker as callable to get the last message. Will be removed in 2.0", \E_USER_DEPRECATED);

        return $this->message;
    }
}
