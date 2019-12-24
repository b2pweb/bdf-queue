<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Message\QueuedMessage;

/**
 * PeekableQueueDriverInterface
 */
interface PeekableQueueDriverInterface extends QueueDriverInterface
{
    /**
     * Inspect a list of messages without interact with their state.
     *
     * @param string $queueName   The queue name to inspect
     * @param int $rowCount       The number of messages to return.
     * @param int $page           The page with the rowCount will determine the offset.
     *
     * @return QueuedMessage[]
     */
    public function peek(string $queueName, int $rowCount = 20, int $page = 1): array;
}
