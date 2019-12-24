<?php

namespace Bdf\Queue\Consumer\Reader;

use Bdf\Queue\Connection\ReservableQueueDriverInterface;
use Bdf\Queue\Message\EnvelopeInterface;

/**
 * Buffering messages from queue
 *
 * When buffer is empty, reserve multiple messages from the queue, and pop messages one by one
 * When calling stop(), the reserved messages will be released
 *
 * @see ReservableQueueDriverInterface
 */
final class BufferedReader implements QueueReaderInterface
{
    /**
     * @var ReservableQueueDriverInterface
     */
    private $driver;

    /**
     * @var string
     */
    private $queue;

    /**
     * @var int
     */
    private $number;

    /**
     * @var EnvelopeInterface[]
     */
    private $cached = [];


    /**
     * PrefetchReader constructor.
     *
     * @param ReservableQueueDriverInterface $driver
     * @param string $queue The queue name
     * @param int $number The buffer size (number of loaded messages)
     */
    public function __construct(ReservableQueueDriverInterface $driver, string $queue, int $number)
    {
        $this->driver = $driver;
        $this->queue = $queue;
        $this->number = $number;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $duration = 0): ?EnvelopeInterface
    {
        if ($envelope = array_shift($this->cached)) {
            return $envelope;
        }

        $this->cached = $this->driver->reserve($this->number, $this->queue, $duration);

        return array_shift($this->cached);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        foreach ($this->cached as $envelope) {
            $envelope->reject(true);
        }

        $this->driver->connection()->close();
    }
}
