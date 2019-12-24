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
    public function receiveStop(): void
    {
        $this->delegate->receiveStop();
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void
    {
        $this->delegate->terminate();
    }
}
