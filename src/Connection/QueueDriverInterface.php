<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * QueueDriverInterface
 */
interface QueueDriverInterface
{
    /**
     * Get the internal connection
     *
     * @return ConnectionDriverInterface
     */
    public function connection(): ConnectionDriverInterface;

    /**
     * Push a message onto the queue.
     *
     * @param Message $message
     */
    public function push(Message $message): void;

    /**
     * Push a raw payload onto the queue.
     *
     * @param mixed $raw
     * @param string $queue
     * @param int $delay
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void;

    /**
     * Pop the next job off of the queue.
     *
     * The connection MUST manage the duration.
     *
     * @param string $queue
     * @param int $duration  Number of seconds to keep polling for messages
     *
     * @return EnvelopeInterface|null
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface;

    /**
     * Acknowledge the message from the queue.
     *
     * @param QueuedMessage $message
     */
    public function acknowledge(QueuedMessage $message): void;

    /**
     * Release a reserved message from the queue. No attempts should be increase.
     *
     * @param QueuedMessage $message
     */
    public function release(QueuedMessage $message): void;

    /**
     * Get the total number of messages
     *
     * Note: May return null if the operation is not supported by the driver
     *
     * @param string $queue
     *
     * @return int
     */
    public function count(string $queue): ?int;

    /**
     * Get the monitoring info
     *
     * Should returns an associative array containing stats by report.
     * Each stats should be an associative array.
     *
     * Note: An empty array is returned if not supported by the driver
     *
     * <code>
     * return ['queues' => ['total' => 15], 'workers' => ['connected' => 2]];
     * </code>
     *
     * @return array
     */
    public function stats(): array;
}
