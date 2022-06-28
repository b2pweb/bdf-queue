<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\QueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Read from multiple queue on same connection, using Round Robin algorithm
 */
final class MultiQueueReader implements QueueReaderInterface
{
    /**
     * @var QueueDriverInterface
     */
    private $driver;

    /**
     * @var string[]
     */
    private $queues;


    /**
     * QueueReader constructor.
     *
     * @param QueueDriverInterface $driver The queue connection
     * @param string[] $queues List of queues to read
     */
    public function __construct(QueueDriverInterface $driver, array $queues)
    {
        $this->driver = $driver;
        $this->queues = $queues;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $duration = 0): ?EnvelopeInterface
    {
        $i = 0;
        $limit = count($this->queues);

        do {
            if (($envelope = $this->driver->pop(current($this->queues), $duration)) !== null) {
                return $envelope;
            }

            if (next($this->queues) === false) {
                reset($this->queues);
            }
        } while (++$i < $limit);

        return null;
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
