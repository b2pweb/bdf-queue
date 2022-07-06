<?php

namespace Bdf\Queue\Destination\Topic;

use Bdf\Dsn\DsnRequest;
use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableDriverInterface;
use Bdf\Queue\Connection\PeekableDriverInterface;
use Bdf\Queue\Destination\DestinationInterface;

/**
 * Factory for topic destinations
 */
final class TopicDestinationFactory
{
    /**
     * Creates the topic destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return TopicDestination|ReadableTopicDestination
     */
    public static function createByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): DestinationInterface
    {
        $driver = $connection->topic();
        $topic = trim($dsn->getPath(), '/');

        return $driver instanceof PeekableDriverInterface || $driver instanceof CountableDriverInterface
            ? new ReadableTopicDestination($driver, $topic)
            : new TopicDestination($driver, $topic)
        ;
    }

    /**
     * Creates the multi topic destination by a DSN
     *
     * @param ConnectionDriverInterface $connection
     * @param DsnRequest $dsn
     *
     * @return MultiTopicDestination
     */
    public static function createMultipleByDsn(ConnectionDriverInterface $connection, DsnRequest $dsn): MultiTopicDestination
    {
        return new MultiTopicDestination($connection->topic(), explode(',', trim($dsn->getPath(), '/')));
    }
}
