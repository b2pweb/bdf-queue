<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Reading strategy for queue
 */
interface QueueReaderInterface
{
    /**
     * Read a message from queue
     *
     * @param int $duration The waiting duration in seconds if the queue is empty. 0 means no waits, and -1 for infinite waiting
     *
     * @return EnvelopeInterface|null The queued message, or null if the queue is empty with a timeout
     */
    public function read(int $duration = 0): ?EnvelopeInterface;

    /**
     * Stop the reader, and close the connection
     */
    public function stop(): void;
}
