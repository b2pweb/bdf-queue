<?php

namespace Bdf\Queue\Consumer;

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
}
