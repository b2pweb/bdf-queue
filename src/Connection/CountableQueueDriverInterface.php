<?php

namespace Bdf\Queue\Connection;

/**
 * CountableQueueDriverInterface
 */
interface CountableQueueDriverInterface
{
    /**
     * Get the total number of messages
     *
     * @param string $queueName   The queue name to inspect
     *
     * @return int
     */
    public function count(string $queueName): int;
}
