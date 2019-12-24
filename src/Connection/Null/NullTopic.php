<?php

namespace Bdf\Queue\Connection\Null;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\TopicDriverInterface;
use Bdf\Queue\Message\Message;

/**
 * NullTopic
 */
class NullTopic implements TopicDriverInterface
{
    use ConnectionBearer;

    /**
     * NullTopic constructor.
     *
     * @param NullConnection $connection
     */
    public function __construct(NullConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function publishRaw(string $topic, $payload): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $duration = ConnectionDriverInterface::DURATION): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(array $topics, callable $callback): void
    {

    }
}
