<?php

namespace Bdf\Queue\Consumer;

use Bdf\Queue\Connection\ConnectionDriverInterface;

/**
 *
 */
interface ConsumerInterface
{
    /**
     * Consuming message queue
     *
     * @param int $duration
     */
    public function consume(int $duration): void;

    /**
     * Stop receiving some messages.
     */
    public function stop(): void;

    /**
     * Get the connection driver instance
     */
    public function connection(): ConnectionDriverInterface;
}
