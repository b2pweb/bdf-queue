<?php

namespace Bdf\Queue\Connection;

/**
 * Base type for queue or topic driver which allows to get number of pending messages
 */
interface CountableDriverInterface
{
    /**
     * Get the total number of messages
     *
     * @param string $name The queue or topic name to inspect
     *
     * @return positive-int|0 Number of pending message, or 0 if there is no messages
     */
    public function count(string $name): int;
}
