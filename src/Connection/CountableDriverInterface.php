<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;

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
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function count(string $name): int;
}
