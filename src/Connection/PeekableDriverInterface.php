<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;
use Bdf\Queue\Message\QueuedMessage;

/**
 * Base type for queue or topic driver which allows retrieving messages without removing from queue or topic
 */
interface PeekableDriverInterface
{
    /**
     * Inspect a list of messages without interact with their state.
     *
     * @param string $name  The queue or topic name to inspect
     * @param int $rowCount The number of messages to return
     * @param int $page     The page with the rowCount will determine the offset. If the page is too high, an empty list will be returned.
     *
     * @return list<QueuedMessage> List of stored message. An empty array is returned if no messages was found.
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function peek(string $name, int $rowCount = 20, int $page = 1): array;
}
