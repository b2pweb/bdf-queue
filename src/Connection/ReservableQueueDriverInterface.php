<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
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
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function reserve(int $number, string $queue, int $duration = ConnectionDriverInterface::DURATION): array;
}
