<?php

namespace Bdf\Queue\Consumer;

/**
 * DelegateHelper
 * helper for receiver facade
 */
trait DelegateHelper
{
    /**
     * @var ReceiverInterface
     */
    private $delegate;

    /**
     * {@inheritdoc}
     */
    public function start(ConsumerInterface $consumer): void
    {
        $this->delegate->start($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->delegate->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $this->delegate->receiveTimeout($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
        $this->delegate->receiveStop($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
        $this->delegate->terminate($consumer);
    }

    /**
     * Debug: show the order of item in the chain list
     *
     * @return string
     */
    public function __toString()
    {
        $next = method_exists($this->delegate, '__toString')
                ? (string) $this->delegate
                : get_class($this->delegate);

        return get_class($this).'->'.$next;
    }
}
