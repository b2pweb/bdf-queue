<?php

namespace Bdf\Queue\Testing;

use Bdf\Queue\Consumer\ConsumerInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 *
 */
class MessageStacker
{
    /**
     * @var EnvelopeInterface[]
     */
    private $messages = [];

    /**
     * @param int $offset
     *
     * @return bool
     */
    public function has(int $offset): bool
    {
        return isset($this->messages[$offset]);
    }

    /**
     * @param int $offset
     *
     * @return EnvelopeInterface
     */
    public function get(int $offset): EnvelopeInterface
    {
        return $this->messages[$offset];
    }

    /**
     * @param EnvelopeInterface $message
     * @param int|null $offset
     *
     * @return void
     */
    public function add(EnvelopeInterface $message, ?int $offset = null): void
    {
        if ($offset !== null) {
            $this->messages[$offset] = $message;
        } else {
            $this->messages[] = $message;
        }
    }

    /**
     * @return int
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

    /**
     * Add a message into the stack.
     * This method is provide for the MessageWatcherReveicer.
     *
     * @param EnvelopeInterface|null $message
     * @param ConsumerInterface $consumer
     * @return void
     */
    public function __invoke(?EnvelopeInterface $message, ConsumerInterface $consumer): void
    {
        if ($message !== null) {
            $this->add($message);
        }
    }
}
