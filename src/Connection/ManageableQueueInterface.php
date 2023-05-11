<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;

/**
 * ManageableQueueInterface
 *
 * Some drive needs to be setup before running.
 * This interface expose the creation and deletion of queues.
 */
interface ManageableQueueInterface
{
    /**
     * Declare a queue
     *
     * @param string $queue
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function declareQueue(string $queue): void;

    /**
     * Declare a queue
     *
     * @param string $queue
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function deleteQueue(string $queue): void;
}
