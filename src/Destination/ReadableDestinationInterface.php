<?php

namespace Bdf\Queue\Destination;

use Bdf\Queue\Message\QueuedMessage;

/**
 * Handle read and write operations on a distant destination
 */
interface ReadableDestinationInterface
{
    /**
     * Get the total number of messages
     *
     * @return int
     */
    public function count(): int;

    /**
     * Inspect a list of messages without interact with their state.
     *
     * @param int $rowCount       The number of messages to return.
     * @param int $page           The page with the rowCount will determine the offset.
     *
     * @return QueuedMessage[]
     */
    public function peek(int $rowCount = 20, int $page = 1): array;
}
