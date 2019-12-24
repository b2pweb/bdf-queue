<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Message\EnvelopeInterface;

/**
 * ReservableQueueDriverInterface
 */
interface ReservableQueueDriverInterface extends QueueDriverInterface
{
    /**
     * Reserve a number of job of a queue
     *
     * @param int $number
     * @param string $queue
     * @param int $duration  Number of seconds to keep polling for messages
     *
     * @return EnvelopeInterface[]
     */
    public function reserve(int $number, string $queue, int $duration = ConnectionDriverInterface::DURATION): array;
}
