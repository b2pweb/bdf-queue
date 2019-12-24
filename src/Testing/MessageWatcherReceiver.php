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
    public function __construct(ReceiverInterface $delegate, callable $callable = null)
    {
        $this->delegate = $delegate;
        $this->callable = $callable;
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

        $this->delegate->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        if ($this->callable !== null) {
            ($this->callable)(null, $consumer);
        }

        $this->delegate->receiveTimeout($consumer);
    }

    /**
     * Gets the last received message
     *
     * @return object|null
     */
    public function getLastMessage()
    {
        return $this->message;
    }
}
