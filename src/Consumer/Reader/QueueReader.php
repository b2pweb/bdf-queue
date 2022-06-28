<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Simple queue reader
 */
final class QueueReader implements QueueReaderInterface
{
    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * @var string
     */
    private $queue;


    /**
     * QueueReader constructor.
     *
     * @param QueueDriverInterface $driver
     * @param string $queue The queue name
     */
    public function __construct(QueueDriverInterface $driver, string $queue)
    {
        $this->driver = $driver;
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $duration = 0): ?EnvelopeInterface
    {
        return $this->driver->pop($this->queue, $duration);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->driver->connection()->close();
    }

    /**
     * {@inheritdoc}
     */
    public function connection(): ConnectionDriverInterface
    {
        return $this->driver->connection();
    }
}
