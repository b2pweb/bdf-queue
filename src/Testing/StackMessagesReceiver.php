<?php

namespace Bdf\Queue\Testing;

use ArrayAccess;
use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Countable;

/**
 * Simple outlet receiver for stack received messages
 *
 * @implements ArrayAccess<int, EnvelopeInterface>
 */
class StackMessagesReceiver implements ReceiverInterface, ArrayAccess, Countable
{
    /**
     * @var list<EnvelopeInterface>
     */
    private $messages = [];

    /**
     * {@inheritdoc}
     */
    public function start(ConsumerInterface $consumer): void
    {
    }

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
    public function receiveTimeout(ConsumerInterface $consumer): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function receiveStop(ConsumerInterface $consumer): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ConsumerInterface $consumer): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
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
    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        throw new \BadMethodCallException();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
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
