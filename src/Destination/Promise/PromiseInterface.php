<?php

namespace Bdf\Queue\Destination\Promise;

use Bdf\Queue\Message\QueuedMessage;

/**
 * Receive replied message from destination.
 * The rpc client sent a message with a replied queue and a correlation id.
 * The promise awaits for message contains the sent correlation_id from the replied queue.
 * If a received message does not match it will be requeued.
 */
interface PromiseInterface
{
    /**
     * Blocks until message received or timeout expired.
     *
     * @param int $timeout The awaiting timeout in milliseconds
     *
     * @return null|QueuedMessage The received reply, or null if no reply is received during waiting time
     */
    public function await(int $timeout = 0): ?QueuedMessage;
}
