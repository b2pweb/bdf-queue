<?php

namespace Bdf\Queue\Connection\Null;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\CountableQueueDriverInterface;
use Bdf\Queue\Connection\Extension\ConnectionBearer;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;
use Bdf\Queue\Message\Message;
use Bdf\Queue\Message\QueuedMessage;

/**
 * NullQueue
 */
class NullQueue implements QueueDriverInterface, CountableQueueDriverInterface
{
    use ConnectionBearer;

    /**
     * NullQueue constructor.
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
    public function push(Message $message): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function pushRaw($raw, string $queue, int $delay = 0): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $duration = ConnectionDriverInterface::DURATION): ?EnvelopeInterface
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function acknowledge(QueuedMessage $message): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function release(QueuedMessage $message): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $queueName): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        return [];
    }
}
