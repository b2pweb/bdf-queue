<?php

namespace Bdf\Queue\Consumer;

/**
 * DelegateHelper
 * helper for receiver facade
 */
trait DelegateHelper
{
    /**
     * @var ReceiverInterface|null
     */
    private $delegate;

    /**
     * {@inheritdoc}
     */
    public function start(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->start($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receive($message, $consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receiveTimeout($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->receiveStop($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
        $next = $this->delegate ?? $consumer;
        $next->terminate($consumer);
    }

    /**
     * Debug: show the order of item in the chain list
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->delegate) {
            return get_class($this);
        }

        $next = method_exists($this->delegate, '__toString')
                ? (string) $this->delegate
                : get_class($this->delegate);

        return get_class($this).'->'.$next;
    }
}
