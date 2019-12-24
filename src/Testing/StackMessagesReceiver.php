<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Simple outlet receiver for stack received messages
 */
class StackMessagesReceiver implements ReceiverInterface, \ArrayAccess, \Countable
{
    /**
     * @var EnvelopeInterface[]
     */
    private $messages = [];

    /**
     * {@inheritdoc}
     */
    public function receive($message, ConsumerInterface $consumer): void
    {
        $this->messages[] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function receiveTimeout(ConsumerInterface $consumer): void { }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(): void { }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void { }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->messages[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): EnvelopeInterface
    {
        return $this->messages[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->messages);
    }

    /**
     * Get all stacked messages
     *
     * @return EnvelopeInterface[]
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Get the last received message, if exists
     *
     * @return EnvelopeInterface|null
     */
    public function last(): ?EnvelopeInterface
    {
        return end($this->messages) ?: null;
    }

    /**
     * Clear the receive stack
     */
    public function clear(): void
    {
        $this->messages = [];
    }
}
