<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Message\Message;

/**
 * TopicDriverInterface
 */
interface TopicDriverInterface
{
    /**
     * Get the internal connection
     *
     * @return ConnectionDriverInterface
     */
    public function connection(): ConnectionDriverInterface;

    /**
     * Publish a message onto the topic.
     *
     * @param Message $message
     */
    public function publish(Message $message): void;

    /**
     * Publish a raw message onto the topic.
     *
     * @param string $topic
     * @param mixed $payload
     */
    public function publishRaw(string $topic, $payload): void;

    /**
     * Consume message from the subscribed channels.
     *
     * @param int $duration     Number of seconds to keep polling for messages
     *
     * @return int The number of consumed messages. Zero for timeout
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int;

    /**
     * Pop the next message off of the queue.
     * Returns an array with message data, message identifier for interaction
     *
     * The callback should await for a the \Bdf\Queue\Message\TopicEnvelope instance
     *
     * @param array $topics          The topics to subscribe
     * @param callable $callback     The callback to consume envelope
     */
    public function subscribe(array $topics, callable $callback): void;
}
