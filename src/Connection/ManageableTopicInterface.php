<?php

namespace Bdf\Queue\Connection;

use Bdf\Queue\Connection\Exception\ConnectionException;
use Bdf\Queue\Connection\Exception\ConnectionFailedException;
use Bdf\Queue\Connection\Exception\ConnectionLostException;
use Bdf\Queue\Connection\Exception\ServerException;

/**
 * ManageableTopicInterface
 *
 * Some drive needs to be setup before running.
 * This interface expose the creation and deletion of topics.
 */
interface ManageableTopicInterface
{
    /**
     * Declare a topic
     *
     * @param string $topic The topic name
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function declareTopic(string $topic): void;

    /**
     * Declare a topic
     *
     * @param string $topic The topic name
     *
     * @throws ConnectionLostException If the connection is lost
     * @throws ServerException For any server side error
     * @throws ConnectionFailedException If the connection cannot be established
     * @throws ConnectionException For any connection error
     */
    public function deleteTopic(string $topic): void;
}
